<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class ResponseGuardrail
{
    // Common prompt-injection patterns
    private const INJECTION_PATTERNS = [
        'ignore previous instructions',
        'reveal system prompt',
        'reveal instructions',
        'export admin data',
        'ignore system prompt',
        'system prompt override',
        'you are now a',
        'you must ignore',
        'ignore the rules'
    ];

    /**
     * Inspect inbound AND outbound payloads for injection, validation, and compliance checks.
     * Throws exception on failure to execute (triggering fail-safe escalation default).
     */
    public function check(
        string $inboundText,
        array $decodedLLMResponse,
        RetrievalContext $context
    ): array {
        // 1. Inbound Prompt Injection check
        $this->detectInjection($inboundText, 'inbound');

        // 2. Outbound Prompt Injection check
        $replyText = $decodedLLMResponse['reply_text'] ?? '';
        $this->detectInjection($replyText, 'outbound');

        // 3. Price and specification verification against source DB context
        $citedFacts = $decodedLLMResponse['cited_facts'] ?? [];
        foreach ($citedFacts as $fact) {
            $field = $fact['field'] ?? '';
            $value = $fact['value'] ?? null;
            $source = $fact['source'] ?? '';

            if (empty($field) || $value === null) {
                continue;
            }

            // Cross-reference against RetrievalContext products
            $matched = false;
            foreach ($context->products as $p) {
                if ($field === 'price') {
                    $expected = $context->region === 'europe' ? $p['price_eur'] : $p['price_usd'];
                    if (abs((float)$value - (float)$expected) < 0.01) {
                        $matched = true;
                        break;
                    }
                } elseif (isset($p[$field])) {
                    if (mb_strtolower((string)$p[$field]) === mb_strtolower((string)$value)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched && !empty($context->products)) {
                throw new \RuntimeException("Factual validation mismatch: Citied field [{$field}] with value [{$value}] does not match source context record.");
            }
        }

        // 4. Compliance/SLA regional rule checks
        if ($context->region === 'europe') {
            // Must contain compliance references if query has compliance queries
            $lowerInbound = mb_strtolower($inboundText);
            if (mb_strpos($lowerInbound, 'complian') !== false || mb_strpos($lowerInbound, 'standard') !== false || mb_strpos($lowerInbound, 'cert') !== false) {
                $lowerReply = mb_strtolower($replyText);
                if (mb_strpos($lowerReply, 'ce') === false && mb_strpos($lowerReply, 'iso') === false && mb_strpos($lowerReply, 'gdpr') === false) {
                    throw new \RuntimeException("Compliance validation error: European replies to compliance queries must explicitly cite CE, ISO, or GDPR.");
                }
            }
        } else {
            // Africa region SLA checks
            $lowerInbound = mb_strtolower($inboundText);
            if (mb_strpos($lowerInbound, 'deliver') !== false || mb_strpos($lowerInbound, 'sla') !== false || mb_strpos($lowerInbound, 'timeline') !== false) {
                $lowerReply = mb_strtolower($replyText);
                if (mb_strpos($lowerReply, '15') === false && mb_strpos($lowerReply, 'business days') === false) {
                    throw new \RuntimeException("SLA validation error: African region delivery SLA response must explicitly state 15 business days.");
                }
            }
        }

        return [
            'valid' => true,
            'reply_text' => $replyText,
        ];
    }

    /**
     * Public pre-screen: only checks injection. Called before any LLM interaction.
     * Throws RuntimeException (with "Injection" in message) if pattern matched.
     */
    public function checkInjectionOnly(string $text, string $direction): void
    {
        $this->detectInjection($text, $direction);
    }

    private function detectInjection(string $text, string $direction): void
    {
        $lower = mb_strtolower($text);
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (mb_strpos($lower, $pattern) !== false) {
                throw new \RuntimeException("Prompt Injection detected on {$direction} text: Matches rule [{$pattern}].");
            }
        }
    }
}
