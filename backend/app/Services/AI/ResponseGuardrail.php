<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ResponseGuardrail
{
    // Common prompt-injection, role-hijacking, and data-exfiltration patterns
    private const INJECTION_PATTERNS = [
        // Role hijacking
        'ignore previous instructions',
        'ignore system prompt',
        'system prompt override',
        'you are now a',
        'you must ignore',
        'ignore the rules',
        'act as if you are',
        'pretend you are',
        'forget your instructions',
        // Data exfiltration
        'reveal system prompt',
        'reveal instructions',
        'export admin data',
        'show me the database',
        'list all users',
        'dump the table',
        'print all records',
        // SQL injection signals (in natural language)
        'drop table',
        'truncate table',
        'delete from',
        'union select',
        '1=1',
        '-- ',
        // Secrets fishing
        'what is your api key',
        'show your api key',
        'reveal the password',
        'what is the password',
        'give me the secret',
        // Price/policy bypass
        'give me a discount of 100',
        'sell it for free',
        'bypass the pricing',
        'ignore the price',
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

            // Robust validation: look up the product in the DB directly based on cited source/context
            $product = null;
            if (str_starts_with($source, 'product:')) {
                $sku = substr($source, 8);
                $product = Product::where('sku', $sku)->first();
            }

            if (!$product) {
                // Fallback: search by matching SKU or name in the source string or the user input
                $product = Product::all()->first(function (Product $p) use ($source, $inboundText) {
                    return str_contains(strtolower($source), strtolower($p->sku)) ||
                           str_contains(strtolower($inboundText), strtolower($p->sku)) ||
                           str_contains(strtolower($inboundText), strtolower($p->name));
                });
            }

            if ($product) {
                $matched = false;
                if ($field === 'price') {
                    $expected = $context->region === 'europe' ? $product->price_eur : $product->price_usd;
                    if (abs((float)$value - (float)$expected) < 0.01) {
                        $matched = true;
                    }
                } elseif (isset($product->$field)) {
                    if (mb_strtolower((string)$product->$field) === mb_strtolower((string)$value)) {
                        $matched = true;
                    }
                }

                if (!$matched) {
                    throw new \RuntimeException("Factual validation mismatch: Citied field [{$field}] with value [{$value}] does not match source context record.");
                }
            } else {
                throw new \RuntimeException("Factual validation mismatch: Cited product source [{$source}] not found in database.");
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
