<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetrievalContext
{
    public function __construct(
        public readonly array $products,   // deprecated — empty in tool-calling path
        public readonly array $passages,   // KB text passages (compliance, SLA, FAQ)
        public readonly string $region,
        public readonly string $language,
    ) {}

    /**
     * Primary entry point for the tool-calling architecture (Layer 7 + Task 3).
     *
     * Retrieval priority:
     *   1. Semantic search via pgvector cosine similarity (when embeddings exist)
     *   2. Substring fallback (for test environments / rows not yet backfilled)
     *
     * Structured facts (price, spec, stock) are NOT fetched here — they arrive
     * exclusively via AgentToolService tool calls during the LLM loop.
     *
     * Region filtering + is_active guard always enforced:
     *  - Cross-region KB content never surfaces
     *  - Inactive (soft-deleted) entries never surface
     */
    public static function buildKbOnly(string $content, string $region, string $language): self
    {
        $passages = self::retrievePassages($content, $region, topK: 8);

        return new self([], $passages, $region, $language);
    }

    /**
     * Internal passage retrieval with semantic-then-substring strategy.
     */
    private static function retrievePassages(string $content, string $region, int $topK): array
    {
        // Attempt semantic retrieval first (requires embeddings on KB rows)
        $hasEmbeddings = DB::table('knowledge_base')
            ->where('region', $region)
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->exists();

        if ($hasEmbeddings) {
            try {
                $embedSvc = app(EmbeddingService::class);
                return $embedSvc->findSimilar($content, $region, $topK);
            } catch (\Throwable $e) {
                // Log and fall through to substring fallback
                Log::channel('production')->warning('Semantic KB retrieval failed, falling back to substring', [
                    'region' => $region,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // Substring fallback (test environments, not-yet-backfilled rows)
        return self::substringPassages($content, $region, $topK);
    }

    /**
     * Substring-based passage retrieval — used as fallback when embeddings unavailable.
     */
    private static function substringPassages(string $content, string $region, int $topK): array
    {
        $normalized = mb_strtolower($content);

        return KnowledgeBase::where('region', $region)
            ->active()
            ->get()
            ->filter(function (KnowledgeBase $kb) use ($normalized) {
                if (strlen($normalized) < 8) {
                    return true;
                }
                return mb_strpos($normalized, mb_strtolower($kb->doc_type)) !== false
                    || ($kb->product_category && mb_strpos($normalized, mb_strtolower($kb->product_category)) !== false)
                    || mb_strpos(mb_strtolower($kb->content), $normalized) !== false
                    || (mb_strpos($normalized, 'complian') !== false && $kb->doc_type === 'compliance')
                    || ((mb_strpos($normalized, 'delivery') !== false || mb_strpos($normalized, 'days') !== false) && $kb->doc_type === 'sla');
            })
            ->take($topK)
            ->map(fn (KnowledgeBase $kb) => [
                'id'               => $kb->id,
                'doc_type'         => $kb->doc_type,
                'product_category' => $kb->product_category,
                'content'          => $kb->content,
            ])
            ->values()
            ->toArray();
    }

    /**
     * @deprecated Use buildKbOnly() for new code.
     *             Kept for backward compatibility with GroundedRAGArchitectureTest.
     */
    public static function build(string $content, string $region, string $language): self
    {
        $normalized = mb_strtolower($content);

        $matchedProducts = Product::all()->filter(function (Product $product) use ($normalized) {
            return mb_strpos($normalized, mb_strtolower($product->name)) !== false
                || mb_strpos($normalized, mb_strtolower($product->sku)) !== false;
        })->map(fn (Product $p) => [
            'id'             => $p->id,
            'name'           => $p->name,
            'sku'            => $p->sku,
            'price_eur'      => (float) $p->price_eur,
            'price_usd'      => (float) $p->price_usd,
            'stock_level'    => (int) $p->stock_level,
            'spec_processor' => $p->spec_processor,
            'spec_ram'       => $p->spec_ram,
            'spec_storage'   => $p->spec_storage,
        ])->values()->toArray();

        $passages = self::substringPassages($content, $region, 5);

        return new self($matchedProducts, $passages, $region, $language);
    }

    /**
     * Serialise context for LLM prompt injection.
     * In tool-calling path (buildKbOnly): only passages — no product data.
     * In deprecated build() path: passages + product pre-fetch (for guardrail tests).
     */
    public function toLLMContext(): array
    {
        $ctx = [
            'region'   => $this->region,
            'language' => $this->language,
            'passages' => array_map(fn ($pass) => [
                'doc_type' => $pass['doc_type'],
                'content'  => $pass['content'],
            ], $this->passages),
        ];

        if (!empty($this->products)) {
            $ctx['products'] = array_map(fn ($p) => [
                'name'           => $p['name'],
                'sku'            => $p['sku'],
                'price'          => $this->region === 'europe' ? $p['price_eur'] . ' EUR' : $p['price_usd'] . ' USD',
                'stock_level'    => $p['stock_level'],
                'spec_processor' => $p['spec_processor'],
                'spec_ram'       => $p['spec_ram'],
                'spec_storage'   => $p['spec_storage'],
            ], $this->products);
        }

        return $ctx;
    }
}
