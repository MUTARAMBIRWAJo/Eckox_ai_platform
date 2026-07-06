<?php

namespace App\Services\AI\Guardrails;

/**
 * ConfidenceScorer — evaluates LLM response confidence and flags low-quality outputs.
 *
 * Inputs from the LLM response:
 *   - 'confidence' field (0.0–1.0) from structured JSON response
 *   - 'cited_facts' field — presence and count of factual citations
 *   - 'reply_text' — length and hedging language analysis
 *
 * Outputs:
 *   - score: float (0.0–1.0), weighted aggregate
 *   - should_escalate: bool (true if score < threshold)
 *   - flags: string[] — list of confidence degradation reasons
 */
class ConfidenceScorer
{
    // Below this score → escalate to human
    private const ESCALATION_THRESHOLD = 0.55;

    // Hedging phrases that indicate low model confidence
    private const HEDGING_PHRASES = [
        'i think', 'i believe', 'i assume', 'probably', 'perhaps', 'might be',
        'not sure', 'i\'m not certain', 'approximately', 'around', 'roughly',
        'je crois', 'je pense', 'peut-être', 'probablement', // French
        'acho que', 'talvez', 'provavelmente',               // Portuguese
    ];

    /**
     * Score a decoded LLM response.
     *
     * @param  array  $decodedResponse The JSON-decoded LLM response array
     * @return array{score: float, should_escalate: bool, flags: string[]}
     */
    public function score(array $decodedResponse): array
    {
        $flags  = [];
        $scores = [];

        // 1. LLM self-reported confidence
        $llmConf = (float) ($decodedResponse['confidence'] ?? 0.5);
        $scores[] = $llmConf;

        if ($llmConf < 0.7) {
            $flags[] = "Low self-reported confidence: {$llmConf}";
        }

        // 2. Factual citation presence
        $citedFacts = $decodedResponse['cited_facts'] ?? [];
        $decision   = $decodedResponse['decision'] ?? 'reply';

        if (in_array($decision, ['reply', 'generate_quote', 'generate_invoice'], true)) {
            if (empty($citedFacts)) {
                $scores[] = 0.65;
                $flags[]  = 'No cited facts in response requiring factual grounding';
            } else {
                $scores[] = min(0.70 + count($citedFacts) * 0.05, 0.95);
            }
        }

        // 3. Hedging language analysis
        $replyText  = mb_strtolower($decodedResponse['reply_text'] ?? '');
        $hedgeCount = 0;

        foreach (self::HEDGING_PHRASES as $phrase) {
            if (str_contains($replyText, $phrase)) {
                $hedgeCount++;
            }
        }

        if ($hedgeCount > 0) {
            $hedgePenalty = min($hedgeCount * 0.08, 0.30);
            $scores[]     = max(0.50, 1.0 - $hedgePenalty);
            $flags[]      = "Hedging language detected ({$hedgeCount} phrases)";
        }

        // 4. Reply completeness (very short replies for non-trivial decisions)
        $replyLength = mb_strlen(trim($decodedResponse['reply_text'] ?? ''));

        if (in_array($decision, ['generate_quote', 'generate_invoice'], true) && $replyLength < 20) {
            $scores[] = 0.55;
            $flags[]  = "Reply too short for decision type: {$decision}";
        }

        // Weighted aggregate (simple average)
        $finalScore = empty($scores) ? 0.5 : array_sum($scores) / count($scores);
        $finalScore = round($finalScore, 4);

        $shouldEscalate = $finalScore < self::ESCALATION_THRESHOLD;

        if ($shouldEscalate) {
            $flags[] = "Score {$finalScore} below escalation threshold " . self::ESCALATION_THRESHOLD;
        }

        return [
            'score'           => $finalScore,
            'should_escalate' => $shouldEscalate,
            'flags'           => $flags,
        ];
    }
}
