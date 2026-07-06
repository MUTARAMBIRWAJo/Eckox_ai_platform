<?php

namespace App\Services\AI;

use App\Models\Lead;
use App\Services\AI\Language\LanguageDetector;
use App\Services\AI\Region\RegionalAdapter;

class AIContextBuilderService
{
    public function __construct(
        private readonly LanguageDetector $languageDetector,
        private readonly RegionalAdapter $regionalAdapter
    ) {}

    /**
     * Detect region from a 2-letter country code.
     */
    public function detectRegion(string $countryCode): string
    {
        return $this->regionalAdapter->detectRegion($countryCode);
    }

    /**
     * Detect language from message content using our enhanced LanguageDetector.
     */
    public function detectLanguage(string $content): string
    {
        return $this->languageDetector->detectCode($content);
    }

    /**
     * Detect intent signals in message content.
     */
    public function detectIntent(string $content): string
    {
        $lower = mb_strtolower($content);

        if (preg_match('/\b(lawyer|legal|sue|lawsuit|court|dispute|complaint|avocat|plainte|tribunal)\b/', $lower)) {
            return 'complaint_legal';
        }

        if (preg_match('/\b(i agree|ok i sign|je signe|d\'accord|aceito|signed|confirm order|go ahead|proceed)\b/', $lower)) {
            return 'agreement';
        }

        if (preg_match('/\b(buy|purchase|order|price|quote|devis|acheter|commander|comprar|préço|cost|how much|combien)\b/', $lower)) {
            return 'buy_intent';
        }

        return 'general';
    }

    /**
     * Build the full structured prompt.
     */
    public function buildPrompt(
        string $region,
        string $language,
        string $content,
        string $intent,
        array  $conversationHistory = [],
        ?Lead  $lead = null,
        ?array $retrievalContextData = null,
    ): array {
        $rules = $this->regionalAdapter->getRules($region);
        $currency = $rules['currency'];
        $payment = implode(', ', $rules['payment_methods']);

        $historyText = $this->formatHistory($conversationHistory);

        $leadContext = $lead
            ? "Lead ID: {$lead->id} | Status: {$lead->status} | Score: {$lead->ai_score}"
            : 'Lead: Unknown — inbound from channel';

        $systemPrompt = <<<SYSTEM
You are an autonomous B2B sales agent for Eckox AI Platform.
Your job is to qualify leads and drive them toward a purchase decision.

{$this->regionalAdapter->buildSystemPromptBlock($region)}

NO-HALLUCINATION GROUND RULE:
- You must ONLY state prices, specs, delivery dates, or compliance claims that appear verbatim in the provided Grounded Retrieval Context.
- If the required answer is not present in the Grounded Retrieval Context, you MUST output a reply stating you will check and follow up shortly. Do NOT guess or invent facts.

GROUNDED RETRIEVAL CONTEXT:
{$this->formatGroundedContext($retrievalContextData)}

CRITICAL OUTPUT RULES:
- You NEVER send messages directly. You return a JSON decision object ONLY.
- Keep reply_text under 40 words.
- If deal value > 100,000 {$currency} → set escalate = true.
- If legal complaint detected → set escalate = true immediately.
- Respond in language: {$language}

OUTPUT FORMAT (MANDATORY — return ONLY this JSON structure, no markdown packaging or extra text):
{
  "intent": "<detected_intent>",
  "decision": "<reply|generate_quote|generate_invoice|escalate|ask_clarification>",
  "confidence": <0.0-1.0>,
  "reply_text": "<max 40 word reply in {$language}>",
  "cited_facts": [
     {"field": "<price|spec_processor|spec_ram|spec_storage>", "value": "<the factual claim value>", "source": "product:<product_sku> or passage:<id>"}
  ],
  "document_required": "<null|quote|invoice|certificate>",
  "escalate": <true|false>,
  "currency": "{$currency}",
  "payment_method": "{$payment}",
  "region": "{$region}",
  "ai_score": "<hot|warm|cold>"
}
SYSTEM;

        return [
            'system'  => $systemPrompt,
            'context' => $leadContext,
            'history' => $historyText,
            'message' => $content,
            'intent'  => $intent,
        ];
    }

    private function formatGroundedContext(?array $contextData): string
    {
        if (empty($contextData)) {
            return "No grounded context available.";
        }
        return json_encode($contextData, JSON_PRETTY_PRINT);
    }

    private function formatHistory(array $history): string
    {
        if (empty($history)) {
            return 'No prior conversation.';
        }

        return collect($history)->map(function ($msg) {
            $role = $msg['role'] ?? 'user';
            return "[{$role}]: {$msg['content']}";
        })->implode("\n");
    }
}
