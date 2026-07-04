<?php

namespace App\Services\AI;

use App\Events\AIDecisionGenerated;
use App\Events\LeadScored;
use App\Models\AiDecision;
use App\Models\InboundMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AIDecisionEngine
{
    public function __construct(
        private readonly AIContextBuilderService $contextBuilder,
        private readonly EscalationGuard         $escalationGuard,
    ) {}

    /**
     * Primary entry point: analyse an inbound message and emit an AI decision.
     */
    public function analyse(InboundMessage $message, ?Lead $lead = null): AiDecision
    {
        $startedAt = microtime(true);
        $traceId   = (string) Str::uuid();

        $region   = $this->contextBuilder->detectRegion($message->country ?? '');
        $language = $message->language ?? $this->contextBuilder->detectLanguage($message->content);
        $intent   = $this->contextBuilder->detectIntent($message->content);

        // Build conversation history from prior inbound messages for this lead
        $history = $lead
            ? $lead->inboundMessages()
                   ->latest()
                   ->take(10)
                   ->get()
                   ->map(fn ($m) => ['role' => 'user', 'content' => $m->content])
                   ->toArray()
            : [];

        $promptPayload = $this->contextBuilder->buildPrompt(
            region:              $region,
            language:            $language,
            content:             $message->content,
            intent:              $intent,
            conversationHistory: $history,
            lead:                $lead,
        );

        // 1. Run the Escalation Guard (Regex OR LLM)
        $guardResult = $this->escalationGuard->check($message, $lead, $region);

        if ($guardResult['escalate']) {
            return $this->forceEscalation($lead, $traceId, $region, $promptPayload, $startedAt, $guardResult);
        }

        // 2. Call LLM for B2B reply decision with structured try/catch parsing safety
        $rawDecision = $this->callOpenAI($promptPayload, $message);

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('production')->info('AI Decision generated', [
            'trace_id'   => $traceId,
            'region'     => $region,
            'channel'    => $message->channel,
            'intent'     => $intent,
            'decision'   => $rawDecision['decision'] ?? 'unknown',
            'confidence' => $rawDecision['confidence'] ?? 0,
            'latency_ms' => $latencyMs,
        ]);

        $decision = AiDecision::create([
            'id'            => (string) Str::uuid(),
            'lead_id'       => $lead?->id,
            'trace_id'      => $traceId,
            'intent'        => $rawDecision['intent'] ?? $intent,
            'region'        => $region,
            'decision_type' => $rawDecision['decision'] ?? 'reply',
            'confidence'    => $rawDecision['confidence'] ?? 0.5,
            'prompt'        => $promptPayload,
            'response'      => $rawDecision,
        ]);

        // Update lead scoring in CRM
        if ($lead && isset($rawDecision['ai_score'])) {
            $lead->update([
                'ai_score'        => $rawDecision['ai_score'],
                'region'          => $region,
                'language'        => $language,
                'last_message_at' => now(),
            ]);

            event(new LeadScored($lead, $rawDecision['ai_score'], $region, $traceId));
        }

        event(new AIDecisionGenerated($decision));

        return $decision;
    }

    /**
     * Call OpenAI and parse structured JSON decision with fallback error recovery.
     */
    private function callOpenAI(array $promptPayload, InboundMessage $message): array
    {
        try {
            $messages = [
                ['role' => 'system', 'content' => $promptPayload['system']],
            ];

            if (!empty($promptPayload['history']) && $promptPayload['history'] !== 'No prior conversation.') {
                $messages[] = ['role' => 'user', 'content' => "Conversation history:\n" . $promptPayload['history']];
            }

            $messages[] = [
                'role'    => 'user',
                'content' => "Lead context: {$promptPayload['context']}\n\nIncoming message: {$promptPayload['message']}",
            ];

            $response = OpenAI::chat()->create([
                'model'       => config('openai.model', 'gpt-4o-mini'),
                'messages'    => $messages,
                'temperature' => 0.3,
                'max_tokens'  => 500,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || !isset($decoded['decision'])) {
                throw new \RuntimeException('Structured decision key missing in LLM response');
            }

            // If LLM returned an explicit escalation field flag, conform it
            if (!empty($decoded['escalate'])) {
                return $this->getEscalationResponse('Escalated directly by decision agent payload instructions.');
            }

            return $decoded;

        } catch (\Throwable $e) {
            Log::channel('production')->error('AI decision LLM call crashed. Defaulting to escalation.', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            return $this->getEscalationResponse('Fail-safe recovery fallback activated: ' . $e->getMessage());
        }
    }

    /**
     * Map structured default escalation response array.
     */
    private function getEscalationResponse(string $reason): array
    {
        return [
            'intent'            => 'complaint_legal',
            'decision'          => 'escalate',
            'confidence'        => 1.0,
            'reply'             => 'Your request has been routed to our manager. A representative will contact you shortly.',
            'document_required' => null,
            'escalate'          => true,
            'ai_score'          => 'warm',
            'reason'            => $reason
        ];
    }

    /**
     * Force an escalation decision.
     */
    private function forceEscalation(
        ?Lead $lead,
        string $traceId,
        string $region,
        array $promptPayload,
        float $startedAt,
        array $guardResult
    ): AiDecision {
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('production')->warning('AI forced escalation hook activated', [
            'trace_id'   => $traceId,
            'region'     => $region,
            'latency_ms' => $latencyMs,
            'reason'     => $guardResult['reason'] ?? 'Unknown',
        ]);

        $responsePayload = $this->getEscalationResponse($guardResult['reason'] ?? 'Security escalation guard rules triggered.');

        $decision = AiDecision::create([
            'id'            => (string) Str::uuid(),
            'lead_id'       => $lead?->id,
            'trace_id'      => $traceId,
            'intent'        => $guardResult['intent'] ?? 'complaint_legal',
            'region'        => $region,
            'decision_type' => 'escalate',
            'confidence'    => 1.0,
            'prompt'        => $promptPayload,
            'response'      => $responsePayload,
        ]);

        event(new AIDecisionGenerated($decision));

        return $decision;
    }
}
