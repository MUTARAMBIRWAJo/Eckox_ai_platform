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
        private readonly ResponseGuardrail       $responseGuardrail,
    ) {}

    /**
     * Primary entry point: analyse an inbound message and emit an AI decision.
     */
    public function analyse(InboundMessage $message, ?Lead $lead = null): AiDecision
    {
        $startedAt = microtime(true);
        $traceId   = (string) Str::uuid();

        $region   = $this->contextBuilder->detectRegion($message->country ?? '');
        // Redact PII (emails/phones/addresses) from inbound message before sending to LLM
        $redactedContent = $this->redactPII($message->content);
        $language = $message->language ?? $this->contextBuilder->detectLanguage($redactedContent);
        $intent   = $this->contextBuilder->detectIntent($redactedContent);

        // 1. Retrieval Layer (RAG): direct product specs and regional compliance context
        $retrievalContext = RetrievalContext::build($redactedContent, $region, $language);

        // Build conversation history (scrubbed of PII)
        $history = $lead
            ? $lead->inboundMessages()
                   ->latest()
                   ->take(10)
                   ->get()
                   ->map(fn ($m) => ['role' => 'user', 'content' => $this->redactPII($m->content)])
                   ->toArray()
            : [];

        $promptPayload = $this->contextBuilder->buildPrompt(
            region:               $region,
            language:             $language,
            content:              $redactedContent,
            intent:               $intent,
            conversationHistory:  $history,
            lead:                 $lead,
            retrievalContextData: $retrievalContext->toLLMContext(),
        );

        // 1a. Prompt-injection pre-screen on RAW inbound text (before any LLM call).
        //     ResponseGuardrail::detectInjection() is called here so we never send
        //     injected content to OpenAI at all, including inside EscalationGuard.
        try {
            $this->responseGuardrail->checkInjectionOnly($message->content, 'inbound');
        } catch (\Throwable $injEx) {
            Log::channel('production')->warning('Prompt injection detected — immediate escalation', [
                'trace_id'   => $traceId,
                'message_id' => $message->id,
                'reason'     => $injEx->getMessage(),
            ]);

            return $this->forceEscalation($lead, $traceId, $region, $promptPayload, $startedAt, [
                'escalate'   => true,
                'reason'     => 'Injection detected: ' . $injEx->getMessage(),
                'confidence' => 1.0,
                'intent'     => 'complaint_legal',
            ]);
        }

        // Run the Escalation Guard (Regex OR LLM)
        $guardResult = $this->escalationGuard->check($message, $lead, $region);

        if ($guardResult['escalate']) {
            return $this->forceEscalation($lead, $traceId, $region, $promptPayload, $startedAt, $guardResult);
        }

        // 2. Call LLM with retry/fallback structures
        $rawDecision = $this->executeLlmFlowWithFallback($promptPayload, $message, $retrievalContext);

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
     * Executes the LLM flow and executes programmatic guardrail validation with fallback retry.
     */
    private function executeLlmFlowWithFallback(
        array $promptPayload,
        InboundMessage $message,
        RetrievalContext $retrievalContext
    ): array {
        // Attempt 1: Call LLM and validate
        $rawDecision = $this->callOpenAI($promptPayload, $message);

        try {
            $this->responseGuardrail->check($message->content, $rawDecision, $retrievalContext);
            return $rawDecision;
        } catch (\Throwable $e) {
            Log::channel('production')->warning('First guardrail check failed, triggering retry fallback', [
                'message_id' => $message->id,
                'error'      => $e->getMessage()
            ]);

            // First Fallback Retry: Restate expected facts clearly in the prompt payload
            $retryPayload = $promptPayload;
            $retryPayload['system'] .= "\n\nCRITICAL RETRY WARNING:\nYou previously generated a response that failed guardrail validation: {$e->getMessage()}.\nYou must resolve this. Ensure cited prices, specs, and compliance references match the provided Grounded Retrieval Context verbatim.";

            $retryDecision = $this->callOpenAI($retryPayload, $message);

            try {
                $this->responseGuardrail->check($message->content, $retryDecision, $retrievalContext);
                return $retryDecision;
            } catch (\Throwable $e2) {
                // Second Fallback: Default to secure pre-defined template response and human escalation
                Log::channel('production')->error('Second guardrail retry failed, forcing human escalation', [
                    'message_id' => $message->id,
                    'error'      => $e2->getMessage()
                ]);

                return $this->getEscalationResponse('Guardrail violation fallback: ' . $e2->getMessage());
            }
        }
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
                'temperature' => 0.1,
                'max_tokens'  => 500,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || (!isset($decoded['decision']) && !isset($decoded['reply_text']))) {
                throw new \RuntimeException('Structured decision key missing in LLM response');
            }

            // Map reply_text key to reply representation
            if (!isset($decoded['decision'])) {
                $decoded['decision'] = 'reply';
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
            'reply_text'        => 'Let me confirm this with our team and follow up shortly.',
            'document_required' => null,
            'escalate'          => true,
            'ai_score'          => 'warm',
            'reason'            => $reason,
            'cited_facts'       => [],
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

    /**
     * Scrub PII from input texts (emails, phones).
     */
    private function redactPII(string $text): string
    {
        // Redact email structures
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        // Redact standard E.164 phone formats
        $text = preg_replace('/\(?\+?[0-9]{1,3}\)?[-.\s]?[0-9]{3,4}[-.\s]?[0-9]{3,4}/', '[REDACTED_PHONE]', $text);

        return $text;
    }
}
