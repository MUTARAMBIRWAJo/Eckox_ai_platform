<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\ResponseGuardrail;
use App\Services\AI\RetrievalContext;
use Illuminate\Support\Facades\Log;

class GuardrailValidationNode implements AgentNode
{
    public function __construct(
        private readonly ResponseGuardrail $responseGuardrail
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'guardrail_validation';
            $state->latencyMs['guardrail_validation'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        try {
            // 1. JSON Structure Validation
            $decision = $state->finalDecision;
            if (!is_array($decision) || (!isset($decision['decision']) && !isset($decision['reply_text']))) {
                throw new \RuntimeException("JSON Structure validation failed: Structured keys missing.");
            }

            // 2. Sensitive Data Leakage Scanning
            $replyText = $decision['reply_text'] ?? '';
            $this->scanForSensitiveLeaks($replyText, $state);

            // 3. Reconstruct RetrievalContext from AgentState array
            $retrievalContext = new RetrievalContext(
                $state->retrievalContext['products'] ?? [],
                $state->retrievalContext['passages'] ?? [],
                $state->region,
                $state->language
            );

            // 4. Run the core ResponseGuardrail checks (price/spec, compliance, injection)
            $verdict = $this->responseGuardrail->check($state->message?->content ?? '', $decision, $retrievalContext);
            $state->guardrailVerdict = $verdict;

        } catch (\Throwable $e) {
            Log::channel('production')->error('Guardrail Validation Node failed', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);

            // Set state to escalated - prevents any downstream action execution
            $state->escalated = true;
            $state->guardrailFailed = true;
            $state->reason = "Guardrail violation: " . $e->getMessage();
            $state->finalDecision = $this->getEscalationResponse($state->reason);
        }

        $state->nodePath[] = 'guardrail_validation';
        $state->latencyMs['guardrail_validation'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    private function scanForSensitiveLeaks(string $text, AgentState $state): void
    {
        $lower = mb_strtolower($text);

        // Check for leaked system instructions / prompt fragments
        $promptKeywords = ['no-hallucination', 'system prompt', 'you must ignore', 'grounded retrieval context'];
        foreach ($promptKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                throw new \RuntimeException("Sensitive Leakage: Response contains system prompt fragments [{$kw}].");
            }
        }

        // Check for leaked UUID structures (potential internal database IDs)
        if (preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $text)) {
            // Throw exception if the UUID is NOT the trace_id (which is okay to share)
            if (!str_contains($text, $state->traceId)) {
                throw new \RuntimeException("Sensitive Leakage: Response contains database UUID internal identifiers.");
            }
        }
    }

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
}
