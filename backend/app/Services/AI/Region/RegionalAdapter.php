<?php

namespace App\Services\AI\Region;

/**
 * RegionalAdapter — centralised regional business rules for the Eckox AI Platform.
 *
 * Regions: africa, europe, middle_east, americas, asia
 *
 * Provides: currency, payment methods, SLA, tone, compliance requirements,
 * tax handling, and business rule descriptors used in system prompt construction.
 */
class RegionalAdapter
{
    private const REGIONS = [
        'africa' => [
            'currency'        => 'USD',
            'payment_methods' => ['Mobile Money (MTN, Orange)', 'Flutterwave', 'M-Pesa'],
            'delivery_days'   => 15,
            'tone'            => 'simple, direct, commercial, energetic',
            'languages'       => ['en', 'fr', 'pt', 'ar', 'sw', 'rw'],
            'compliance'      => [],
            'gdpr'            => false,
            'tax_note'        => 'Import duties may apply. VAT per local jurisdiction.',
            'sla_note'        => '15 business days standard delivery.',
            'country_codes'   => [
                'NG', 'GH', 'KE', 'SN', 'CI', 'CM', 'MA', 'DZ', 'TN',
                'EG', 'ZA', 'TZ', 'RW', 'UG', 'ET', 'MZ', 'AO', 'CD',
            ],
        ],
        'europe' => [
            'currency'        => 'EUR',
            'payment_methods' => ['Stripe', 'SEPA bank transfer', 'Wire transfer'],
            'delivery_days'   => 10,
            'tone'            => 'formal, precise, compliance-aware',
            'languages'       => ['en', 'fr', 'de', 'it', 'es', 'pt'],
            'compliance'      => ['CE marking', 'ISO 17025', 'GDPR'],
            'gdpr'            => true,
            'tax_note'        => 'Prices exclusive of VAT unless stated. EU reverse charge may apply.',
            'sla_note'        => '10 business days standard delivery. Expedited available.',
            'country_codes'   => [
                'FR', 'DE', 'IT', 'ES', 'PT', 'BE', 'NL', 'GB', 'CH',
                'AT', 'SE', 'NO', 'DK', 'FI', 'PL', 'CZ', 'RO', 'HU',
            ],
        ],
        'middle_east' => [
            'currency'        => 'USD',
            'payment_methods' => ['Wire transfer', 'SWIFT', 'Local bank transfer'],
            'delivery_days'   => 12,
            'tone'            => 'formal, respectful, relationship-focused',
            'languages'       => ['ar', 'en', 'fr'],
            'compliance'      => ['ISO 9001', 'Halal certification where applicable'],
            'gdpr'            => false,
            'tax_note'        => 'VAT applicable in UAE/KSA. Other jurisdictions vary.',
            'sla_note'        => '12 business days standard delivery.',
            'country_codes'   => ['AE', 'SA', 'QA', 'KW', 'BH', 'OM', 'JO', 'LB', 'EG'],
        ],
        'americas' => [
            'currency'        => 'USD',
            'payment_methods' => ['Stripe', 'Wire transfer', 'ACH', 'Credit card'],
            'delivery_days'   => 10,
            'tone'            => 'professional, direct, results-oriented',
            'languages'       => ['en', 'es', 'pt'],
            'compliance'      => ['FCC', 'UL certification'],
            'gdpr'            => false,
            'tax_note'        => 'Sales tax or import duties per destination state/country.',
            'sla_note'        => '10 business days standard delivery.',
            'country_codes'   => ['US', 'CA', 'MX', 'BR', 'AR', 'CO', 'CL', 'PE'],
        ],
        'asia' => [
            'currency'        => 'USD',
            'payment_methods' => ['Wire transfer', 'SWIFT', 'Alipay (CN)', 'PayNow (SG)'],
            'delivery_days'   => 14,
            'tone'            => 'respectful, patient, detail-oriented',
            'languages'       => ['en', 'zh', 'ja', 'ko'],
            'compliance'      => ['CE', 'RoHS', 'CCC (China)'],
            'gdpr'            => false,
            'tax_note'        => 'Import duties and GST/VAT per destination country.',
            'sla_note'        => '14 business days standard delivery.',
            'country_codes'   => ['CN', 'JP', 'KR', 'IN', 'SG', 'MY', 'TH', 'VN', 'ID'],
        ],
    ];

    /**
     * Detect region from a 2-letter ISO country code.
     */
    public function detectRegion(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));

        foreach (self::REGIONS as $region => $config) {
            if (in_array($countryCode, $config['country_codes'], true)) {
                return $region;
            }
        }

        return 'africa'; // Default
    }

    /**
     * Return the full rules config for a region.
     */
    public function getRules(string $region): array
    {
        return self::REGIONS[$region] ?? self::REGIONS['africa'];
    }

    /**
     * Return the currency for a region.
     */
    public function getCurrency(string $region): string
    {
        return self::REGIONS[$region]['currency'] ?? 'USD';
    }

    /**
     * Return payment methods for a region as a comma-separated string.
     */
    public function getPaymentMethods(string $region): string
    {
        $methods = self::REGIONS[$region]['payment_methods'] ?? ['Wire transfer'];
        return implode(', ', $methods);
    }

    /**
     * Return whether GDPR compliance is required for a region.
     */
    public function requiresGdpr(string $region): bool
    {
        return self::REGIONS[$region]['gdpr'] ?? false;
    }

    public function buildSystemPromptBlock(string $region): string
    {
        $rules    = $this->getRules($region);
        $regionUc = strtoupper($region);
        $compl    = empty($rules['compliance']) ? 'None' : implode(', ', $rules['compliance']);
        $langList = implode(', ', $rules['languages'] ?? []);

        return <<<BLOCK
{$regionUc} REGION RULES:
- Currency: {$rules['currency']} only
- Payment methods: {$this->getPaymentMethods($region)}
- Delivery timeline: {$rules['delivery_days']} business days
- Tone: {$rules['tone']}
- Language: auto-detect from [{$langList}]
- Compliance required: {$compl}
- Tax note: {$rules['tax_note']}
- SLA: {$rules['sla_note']}
BLOCK;
    }
}
