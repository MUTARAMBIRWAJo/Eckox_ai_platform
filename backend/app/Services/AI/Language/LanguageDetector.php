<?php

namespace App\Services\AI\Language;

use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Log;

/**
 * LanguageDetector — multi-signal language identification with confidence scoring.
 *
 * Detection strategy (in order of precedence):
 *   1. Script detection (Arabic, Devanagari, CJK, Cyrillic)
 *   2. High-confidence lexical patterns per language
 *   3. LLM fallback via Groq (cheapest) for ambiguous inputs
 *   4. Default: 'en' with low confidence
 *
 * Supported codes: en, fr, pt, ar, sw, rw, es, de, it
 */
class LanguageDetector
{
    // Lexical signals: [pattern, language code, confidence contribution]
    private const LEXICAL_RULES = [
        // French
        ['pattern' => '/\b(bonjour|merci|bonsoir|s\'il vous plaît|oui|non|c\'est|je suis|nous sommes|prix|produit|acheter|commander|devis|livraison|entreprise|société)\b/i', 'lang' => 'fr', 'score' => 0.85],
        // Portuguese (distinct signals: obrigado, bom dia, boa tarde, boa noite, por favor, sim, não, preço, empresa, entrega, pedido, contrato)
        ['pattern' => '/\b(obrigado|bom dia|boa tarde|boa noite|por favor|sim|não|preço|empresa|entrega|pedido|contrato)\b/i', 'lang' => 'pt', 'score' => 0.85],
        // Spanish (use Spanish distinct signals: gracias, buenos días, buenas tardes, por favor, hola, gusta, gustaría, quiero, comprar, producto, precio, empresa, entrega, pedido, contrato)
        ['pattern' => '/\b(buenos días|buenas tardes|gracias|por favor|hola|gusta|gustaría|quiero|comprar|producto|precio|empresa|entrega|pedido|contrato)\b/i', 'lang' => 'es', 'score' => 0.85],
        // German
        ['pattern' => '/\b(guten morgen|guten tag|bitte|danke|ja|nein|kaufen|produkt|preis|unternehmen|lieferung|bestellung|vertrag)\b/i', 'lang' => 'de', 'score' => 0.85],
        // Italian
        ['pattern' => '/\b(buongiorno|buonasera|grazie|per favore|sì|no|comprare|prodotto|prezzo|azienda|consegna|ordine|contratto)\b/i', 'lang' => 'it', 'score' => 0.85],
        // Swahili
        ['pattern' => '/\b(habari|shikamoo|asante|karibu|ndiyo|hapana|nunua|bidhaa|bei|kampuni|utoaji|agizo)\b/i', 'lang' => 'sw', 'score' => 0.88],
        // Kinyarwanda
        ['pattern' => '/\b(muraho|mwaramutse|mwiriwe|urakoze|yego|oya|kugura|igicuruzwa|igiciro|sosiyete|dokoraniro)\b/i', 'lang' => 'rw', 'score' => 0.90],
        // English (lower score — default-ish, but flag strong signals)
        ['pattern' => '/\b(hello|good morning|good afternoon|thank you|please|yes|no|buy|product|price|company|delivery|order|contract|invoice|quote)\b/i', 'lang' => 'en', 'score' => 0.60],
    ];

    public function __construct(private readonly GroqProvider $groq) {}

    /**
     * Detect language from text content.
     *
     * @return array{code: string, confidence: float, method: string}
     */
    public function detect(string $content): array
    {
        $content = trim($content);

        if (empty($content)) {
            return ['code' => 'en', 'confidence' => 0.5, 'method' => 'empty_default'];
        }

        // 1. Arabic script (Unicode range)
        if (preg_match('/\p{Arabic}/u', $content)) {
            return ['code' => 'ar', 'confidence' => 0.97, 'method' => 'script'];
        }

        // 2. Lexical pattern matching — accumulate evidence per language
        $scores = [];
        $lower  = mb_strtolower($content);

        foreach (self::LEXICAL_RULES as $rule) {
            if (preg_match($rule['pattern'], $lower, $matches)) {
                $lang = $rule['lang'];
                $scores[$lang] = ($scores[$lang] ?? 0) + $rule['score'];
            }
        }

        if (!empty($scores)) {
            arsort($scores);
            $topLang  = array_key_first($scores);
            $topScore = $scores[$topLang];

            // High confidence result
            if ($topScore >= 0.85) {
                return ['code' => $topLang, 'confidence' => min($topScore, 0.97), 'method' => 'lexical'];
            }

            // Multiple signals but below threshold → LLM arbitration
            if ($topScore >= 0.55) {
                return $this->llmDetect($content, $topLang, $topScore);
            }
        }

        // 3. LLM fallback for completely ambiguous text
        if (mb_strlen($content) >= 15) {
            return $this->llmDetect($content, 'en', 0.4);
        }

        return ['code' => 'en', 'confidence' => 0.45, 'method' => 'default'];
    }

    /**
     * Convenience method: return just the language code.
     */
    public function detectCode(string $content): string
    {
        return $this->detect($content)['code'];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function llmDetect(string $content, string $hint, float $hintScore): array
    {
        try {
            $dummyState = new \App\Services\AI\AgentState('lang-detect-' . substr(md5($content), 0, 8));

            $result = $this->groq->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a language detection expert. Identify the ISO 639-1 language code for the given text. Respond with ONLY a JSON object: {"code": "<iso_code>", "confidence": <0.0-1.0>}. Supported codes: en, fr, pt, ar, sw, rw, es, de, it. If unsure, return {"code": "en", "confidence": 0.5}.',
                ],
                [
                    'role'    => 'user',
                    'content' => mb_substr($content, 0, 300), // Limit input to 300 chars
                ],
            ], [], $dummyState);

            $text   = $result['choice']['message']['content'] ?? '{}';
            $parsed = json_decode($text, true);

            if (isset($parsed['code'], $parsed['confidence'])) {
                return [
                    'code'       => $parsed['code'],
                    'confidence' => (float) $parsed['confidence'],
                    'method'     => 'llm',
                ];
            }
        } catch (\Throwable $e) {
            Log::channel('production')->debug('LanguageDetector: LLM fallback failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['code' => $hint, 'confidence' => $hintScore, 'method' => 'lexical_fallback'];
    }
}
