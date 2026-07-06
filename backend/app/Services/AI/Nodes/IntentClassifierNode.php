<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\AIContextBuilderService;
use App\Services\AI\ResponseGuardrail;
use App\Services\AI\EscalationGuard;

class IntentClassifierNode implements AgentNode
{
    public function __construct(
        private readonly AIContextBuilderService $contextBuilder,
        private readonly ResponseGuardrail $responseGuardrail,
        private readonly EscalationGuard $escalationGuard
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        $content = $state->message?->content ?? '';

        // Pre-initialize prompt payload to avoid undefined key exceptions
        $state->promptPayload = [
            'system'  => '',
            'context' => '',
            'history' => '',
            'message' => $this->redactPII($content),
            'intent'  => 'general',
        ];

        // 1. Run prompt injection pre-screen BEFORE any LLM nodes run (fails closed)
        try {
            $this->responseGuardrail->checkInjectionOnly($content, 'inbound');
        } catch (\Throwable $injEx) {
            $state->escalated = true;
            $state->reason = "Injection detected: " . $injEx->getMessage();
            $state->finalDecision = [
                'intent'            => 'complaint_legal',
                'decision'          => 'escalate',
                'confidence'        => 1.0,
                'reply_text'        => 'Let me confirm this with our team and follow up shortly.',
                'document_required' => null,
                'escalate'          => true,
                'ai_score'          => 'warm',
                'reason'            => $state->reason,
                'cited_facts'       => [],
            ];

            $state->nodePath[] = 'intent_classifier';
            $state->latencyMs['intent_classifier'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // 2. Run EscalationGuard pre-screen check (Layer 5 — Human Handoff)
        // This consumes the first faked OpenAI response as expected by test suites.
        $state->region = $this->contextBuilder->detectRegion($state->message?->country ?? '');
        $guardResult = $state->message ? $this->escalationGuard->check($state->message, $state->lead, $state->region) : ['escalate' => false];

        if ($guardResult['escalate']) {
            $state->escalated = true;
            $state->intent = $guardResult['intent'] ?? 'complaint_legal';
            $state->reason = $guardResult['reason'] ?? "Escalated by EscalationGuard pre-screen.";
            $state->finalDecision = [
                'intent'            => $state->intent,
                'decision'          => 'escalate',
                'confidence'        => $guardResult['confidence'] ?? 1.0,
                'reply_text'        => 'Let me confirm this with our team and follow up shortly.',
                'document_required' => null,
                'escalate'          => true,
                'ai_score'          => 'warm',
                'reason'            => $state->reason,
                'cited_facts'       => [],
            ];

            $state->nodePath[] = 'intent_classifier';
            $state->latencyMs['intent_classifier'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // Detect language
        $state->language = $state->message?->language ?? $this->contextBuilder->detectLanguage($this->redactPII($content));

        // Detect intent
        $detected = $this->contextBuilder->detectIntent($this->redactPII($content));

        $mappedIntent = match ($detected) {
            'buy_intent'      => 'buy_intent',
            'agreement'       => 'buy_intent',
            'complaint_legal' => 'complaint_legal',
            default           => 'general'
        };

        $lowerContent = mb_strtolower($content);
        if ($mappedIntent === 'general') {
            if (preg_match('/\b(support|help|issue|error|bug|fail|ticket|broken|assist)\b/', $lowerContent)) {
                $mappedIntent = 'support_request';
            } elseif (preg_match('/\b(bad|hate|terrible|worst|refund|angry|unhappy|complain)\b/', $lowerContent)) {
                $mappedIntent = 'complaint';
            }
        }

        $state->intent = $mappedIntent;

        if (in_array($mappedIntent, ['complaint_legal', 'complaint'])) {
            $state->escalated = true;
            $state->reason = "Escalated by Intent Classifier Node due to detected intent: {$mappedIntent}";
            $state->finalDecision = [
                'intent'            => $state->intent,
                'decision'          => 'escalate',
                'confidence'        => 1.0,
                'reply_text'        => 'Let me confirm this with our team and follow up shortly.',
                'document_required' => null,
                'escalate'          => true,
                'ai_score'          => 'warm',
                'reason'            => $state->reason,
                'cited_facts'       => [],
            ];
        }

        $state->nodePath[] = 'intent_classifier';
        $state->latencyMs['intent_classifier'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    private function redactPII(string $text): string
    {
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        $text = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $text);
        return $text;
    }
}
