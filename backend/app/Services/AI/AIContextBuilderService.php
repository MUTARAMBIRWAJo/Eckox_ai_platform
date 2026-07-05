<?php

namespace App\Services\AI;

use App\Models\Lead;

class AIContextBuilderService
{
    // Map of country codes to region
    private const COUNTRY_REGION_MAP = [
        'NG' => 'africa', 'GH' => 'africa', 'KE' => 'africa',
        'SN' => 'africa', 'CI' => 'africa', 'CM' => 'africa',
        'MA' => 'africa', 'DZ' => 'africa', 'TN' => 'africa',
        'EG' => 'africa', 'ZA' => 'africa', 'TZ' => 'africa',
        'FR' => 'europe', 'DE' => 'europe', 'IT' => 'europe',
        'ES' => 'europe', 'PT' => 'europe', 'BE' => 'europe',
        'NL' => 'europe', 'GB' => 'europe', 'CH' => 'europe',
    ];

    private const AFRICA_RULES = <<<RULES
AFRICA REGION RULES:
- Currency: USD only
- Payment methods: Mobile Money (MTN, Orange), Flutterwave
- Delivery timeline: 15 business days
- Tone: simple, direct, commercial, energetic
- Language: auto-detect FR / EN / PT / AR
- Legal: no EU compliance required
RULES;

    private const EUROPE_RULES = <<<RULES
EUROPE REGION RULES:
- Currency: EUR only
- Payment methods: Stripe, bank transfer (SEPA)
- Delivery timeline: 10 business days
- Tone: formal, precise, compliance-aware
- Compliance required: CE marking, ISO 17025, GDPR
- Language: auto-detect FR / EN / DE / IT
- Legal: include GDPR data processing notice
RULES;

    /**
     * Detect region from a 2-letter country code.
     */
    public function detectRegion(string $countryCode): string
    {
        return self::COUNTRY_REGION_MAP[strtoupper($countryCode)] ?? 'africa';
    }

    /**
     * Detect language from message content using simple heuristics.
     */
    public function detectLanguage(string $content): string
    {
        $content = mb_strtolower($content);

        // Arabic script detection
        if (preg_match('/\p{Arabic}/u', $content)) {
            return 'ar';
        }

        // Portuguese signals
        if (preg_match('/\b(obrigado|bom dia|boa tarde|comprar|produto|preço)\b/', $content)) {
            return 'pt';
        }

        // French signals
        if (preg_match('/\b(bonjour|merci|prix|produit|acheter|commander|devis)\b/', $content)) {
            return 'fr';
        }

        return 'en';
    }

    /**
     * Detect intent signals in message content.
     *
     * @return string 'buy_intent'|'agreement'|'complaint_legal'|'general'
     */
    public function detectIntent(string $content): string
    {
        $lower = mb_strtolower($content);

        // Legal / complaint escalation signals
        if (preg_match('/\b(lawyer|legal|sue|lawsuit|court|dispute|complaint|avocat|plainte|tribunal)\b/', $lower)) {
            return 'complaint_legal';
        }

        // Agreement signals
        if (preg_match('/\b(i agree|ok i sign|je signe|d\'accord|aceito|signed|confirm order|go ahead|proceed)\b/', $lower)) {
            return 'agreement';
        }

        // Purchase intent signals
        if (preg_match('/\b(buy|purchase|order|price|quote|devis|acheter|commander|comprar|préço|cost|how much|combien)\b/', $lower)) {
            return 'buy_intent';
        }

        return 'general';
    }

    /**
     * Build the full structured prompt for the AI decision engine grounded strictly in RetrievalContext.
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
        $regionRules = $region === 'europe' ? self::EUROPE_RULES : self::AFRICA_RULES;
        $currency    = $region === 'europe' ? 'EUR' : 'USD';
        $payment     = $region === 'europe' ? 'Stripe' : 'Mobile Money / Flutterwave';

        $historyText = $this->formatHistory($conversationHistory);

        // Scrub lead name & email for privacy validation layers (GDPR)
        $leadContext = $lead
            ? "Lead ID: {$lead->id} | Status: {$lead->status} | Score: {$lead->ai_score}"
            : 'Lead: Unknown — inbound from channel';

        $systemPrompt = <<<SYSTEM
You are an autonomous B2B sales agent for Eckox AI Platform.
Your job is to qualify leads and drive them toward a purchase decision.

{$regionRules}

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
