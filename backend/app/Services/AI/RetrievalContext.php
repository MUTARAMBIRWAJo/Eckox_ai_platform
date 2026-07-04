<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\KnowledgeBase;
use Illuminate\Support\Str;

class RetrievalContext
{
    public function __construct(
        public readonly array $products,
        public readonly array $passages,
        public readonly string $region,
        public readonly string $language,
    ) {}

    /**
     * Helper to construct context securely from basic input search logic.
     */
    public static function build(string $content, string $region, string $language): self
    {
        $normalized = mb_strtolower($content);

        // 1. Precise structured products lookup (Direct SKU / Name substring matches)
        $matchedProducts = Product::all()->filter(function (Product $product) use ($normalized) {
            $nameMatch = mb_strpos($normalized, mb_strtolower($product->name)) !== false;
            $skuMatch  = mb_strpos($normalized, mb_strtolower($product->sku)) !== false;
            return $nameMatch || $skuMatch;
        })->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'price_eur' => (float) $p->price_eur,
            'price_usd' => (float) $p->price_usd,
            'stock_level' => (int) $p->stock_level,
            'spec_processor' => $p->spec_processor,
            'spec_ram' => $p->spec_ram,
            'spec_storage' => $p->spec_storage,
        ])->values()->toArray();

        // 2. Query region-appropriate Knowledge Base entries (simulated top-k vector text chunk passages)
        //    Only fetch is_active=true entries to prevent stale KB facts from surfacing.
        $passages = KnowledgeBase::where('region', $region)
            ->active()
            ->get()
            ->filter(function (KnowledgeBase $kb) use ($normalized) {
                // If query is broad, fetch all. Otherwise filter by match relevance
                if (strlen($normalized) < 8) {
                    return true;
                }
                // Check simple substring match or tags
                return mb_strpos($normalized, mb_strtolower($kb->doc_type)) !== false ||
                       ($kb->product_category && mb_strpos($normalized, mb_strtolower($kb->product_category)) !== false) ||
                       mb_strpos(mb_strtolower($kb->content), $normalized) !== false ||
                       // Always match SLA and compliance rules if relevant words exist
                       (mb_strpos($normalized, 'complain') !== false && $kb->doc_type === 'compliance') ||
                       ((mb_strpos($normalized, 'delivery') !== false || mb_strpos($normalized, 'days') !== false) && $kb->doc_type === 'sla');
            })
            ->take(5)
            ->map(fn (KnowledgeBase $kb) => [
                'id' => $kb->id,
                'doc_type' => $kb->doc_type,
                'product_category' => $kb->product_category,
                'content' => $kb->content,
            ])
            ->values()
            ->toArray();

        return new self($matchedProducts, $passages, $region, $language);
    }

    /**
     * Map clean context DTO structure strictly for the LLM payload (Privacy safe).
     */
    public function toLLMContext(): array
    {
        return [
            'region'   => $this->region,
            'language' => $this->language,
            'products' => array_map(fn ($p) => [
                'name' => $p['name'],
                'sku' => $p['sku'],
                'price' => $this->region === 'europe' ? $p['price_eur'] . ' EUR' : $p['price_usd'] . ' USD',
                'stock_level' => $p['stock_level'],
                'spec_processor' => $p['spec_processor'],
                'spec_ram' => $p['spec_ram'],
                'spec_storage' => $p['spec_storage'],
            ], $this->products),
            'passages' => array_map(fn ($pass) => [
                'doc_type' => $pass['doc_type'],
                'content' => $pass['content'],
            ], $this->passages)
        ];
    }
}
