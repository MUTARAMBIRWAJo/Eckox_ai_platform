<?php

namespace App\Services\AI;

use App\Models\InboundMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class EscalationGuard
{
    public function __construct(
        private readonly AIContextBuilderService $contextBuilder,
    ) {}

    /**
     * Determine if a message requires human escalation.
     * Combines regex check OR structured LLM classification check.
     * On failure to parse/validate LLM output, defaults to escalate = true (fail-safe).
     */
    public function check(InboundMessage $message, ?Lead $lead = null, string $region = 'africa'): array
    {
        // 1. Existing Regex Pre-Filter
        $regexIntent = $this->contextBuilder->detectIntent($message->content);
        $regexEscalate = ($regexIntent === 'complaint_legal');

        // 2. LLM-Based Classification
        $llmEscalate = false;
        $reason = 'LLM check skipped or benign';
        $confidence = 1.0;

        try {
            $currency = $region === 'europe' ? 'EUR' : 'USD';
            $systemPrompt = <<<SYSTEM
You are a security and safety classifier for a B2B CRM.
Your job is to read user inbound messages and determine if they require human escalation.

Escalation Criteria:
- Message is a legal complaint, threat of litigation, or legal threat.
- Message involves a deal or transaction value greater than 100,000 {$currency}.
- Message expresses high frustration, complaint, or request for a human manager.
- Message contains public tender/RFP bid signals.

You must respond with a JSON object matching this exact schema:
{
  "requires_human_escalation": <bool>,
  "reason": "<short string explaining the categorization>",
  "confidence": <float between 0.0 and 1.0>
}
SYSTEM;

            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "Incoming message: {$message->content}"],
                ],
                'temperature' => 0.1,
                'max_tokens' => 200,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || !isset($decoded['requires_human_escalation'])) {
                throw new \RuntimeException('Missing requires_human_escalation key in LLM response');
            }

            $llmEscalate = (bool) $decoded['requires_human_escalation'];
            $reason      = $decoded['reason'] ?? 'No reason provided';
            $confidence  = (float) ($decoded['confidence'] ?? 1.0);

        } catch (\Throwable $e) {
            Log::channel('production')->warning('Escalation LLM check failed. Defaulting to true (fail-safe).', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
            // Fail-safe: default to escalation on error
            $llmEscalate = true;
            $reason      = 'Escalation guard fallback: LLM validation error — ' . $e->getMessage();
            $confidence  = 1.0;
        }

        $escalate = $regexEscalate || $llmEscalate;
        $intent = 'general';
        if ($regexEscalate) {
            $intent = 'complaint_legal';
        } elseif ($llmEscalate) {
            $intent = 'escalated_by_llm';
        }

        return [
            'escalate'   => $escalate,
            'reason'     => $reason,
            'confidence' => $confidence,
            'intent'     => $intent,
        ];
    }
}
