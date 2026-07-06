<?php

namespace App\Services\AI\Privacy;

/**
 * PIIRedactor — centralised PII masking before any data touches the LLM.
 *
 * Masks: email addresses, phone numbers, IBAN, credit card numbers,
 * national ID patterns (passport, SSN, national ID), and API key shapes.
 *
 * NEVER pass the raw output of this service to a log channel at level >= info.
 * This output is safe for LLM consumption only.
 */
class PIIRedactor
{
    private const PATTERNS = [
        // Email addresses
        'email'   => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
        // International phone numbers (E.164 and local formats)
        'phone'   => '/(?:\+?[\d\s\-().]{7,}\d)/u',
        // IBAN (European bank accounts)
        'iban'    => '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/u',
        // Credit/debit card numbers (Luhn-ish pattern, 13–19 digits with optional spaces/dashes)
        'card'    => '/\b(?:\d[ \-]?){13,19}\b/u',
        // Passport numbers (generic: 1-2 letters + 5-9 digits)
        'passport' => '/\b[A-Z]{1,2}\d{5,9}\b/u',
        // US SSN
        'ssn'     => '/\b\d{3}[-\s]?\d{2}[-\s]?\d{4}\b/u',
        // API key shapes (accidental inclusion protection)
        'api_key' => '/(sk-[A-Za-z0-9\-_]{20,}|gsk_[A-Za-z0-9]{20,}|ghp_[A-Za-z0-9]{20,}|Bearer\s+\S{20,})/u',
    ];

    private const REPLACEMENTS = [
        'email'    => '[REDACTED_EMAIL]',
        'phone'    => '[REDACTED_PHONE]',
        'iban'     => '[REDACTED_IBAN]',
        'card'     => '[REDACTED_CARD]',
        'passport' => '[REDACTED_ID]',
        'ssn'      => '[REDACTED_SSN]',
        'api_key'  => '[REDACTED_KEY]',
    ];

    /**
     * Redact all PII patterns from the given text.
     * Returns the sanitised string, safe for LLM transmission.
     */
    public function redact(string $text): string
    {
        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace($pattern, self::REPLACEMENTS[$type], $text) ?? $text;
        }
        return $text;
    }

    /**
     * Redact only specific PII types.
     * @param string[] $types Keys from PATTERNS: 'email', 'phone', 'iban', etc.
     */
    public function redactOnly(string $text, array $types): string
    {
        foreach ($types as $type) {
            if (isset(self::PATTERNS[$type])) {
                $text = preg_replace(self::PATTERNS[$type], self::REPLACEMENTS[$type], $text) ?? $text;
            }
        }
        return $text;
    }

    /**
     * Return true if the text contains any detectable PII.
     */
    public function containsPII(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
}
