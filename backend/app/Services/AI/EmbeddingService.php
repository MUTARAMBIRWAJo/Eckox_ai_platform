<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use App\Services\AI\RAG\EmbeddingCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * EmbeddingService — generates and stores OpenAI text embeddings for Knowledge Base entries.
 *
 * Model: text-embedding-3-small (1536 dimensions, lowest cost, good quality for KB retrieval).
 * Storage: pgvector column on knowledge_base table.
 * Retrieval: cosine similarity via HNSW index.
 * Caching: Redis-backed EmbeddingCache (7-day TTL) to avoid redundant API calls.
 */
class EmbeddingService
{
    private const DIMENSIONS = 1536;

    public function __construct(private readonly EmbeddingCache $cache) {}

    /**
     * Return the configured embedding model name.
     */
    public function getModel(): string
    {
        return config('llm.models.openai.embedding', 'text-embedding-3-small');
    }

    /**
     * Generate an embedding vector for arbitrary text.
     * Returns a float[] of length DIMENSIONS.
     * Results are cached in Redis for config('llm.cache.embeddings_ttl') seconds.
     */
    public function embed(string $text): array
    {
        // Cache hit?
        $cached = $this->cache->get($text);
        if ($cached !== null) {
            return $cached;
        }

        $response = OpenAI::embeddings()->create([
            'model' => $this->getModel(),
            'input' => $text,
        ]);

        $vector = $response->embeddings[0]->embedding;

        // Store in cache
        $this->cache->put($text, $vector);

        return $vector;
    }

    /**
     * Generate and persist an embedding for a single KnowledgeBase record.
     */
    public function embedAndStore(KnowledgeBase $kb): void
    {
        $vector = $this->embed($kb->content);

        // Store as pgvector literal: '[0.1, 0.2, ...]'
        DB::table('knowledge_base')
            ->where('id', $kb->id)
            ->update(['embedding' => '[' . implode(',', $vector) . ']']);

        Log::channel('production')->info('KB embedding stored', [
            'kb_id'      => $kb->id,
            'doc_type'   => $kb->doc_type,
            'region'     => $kb->region,
            'dimensions' => count($vector),
        ]);
    }

    /**
     * Find the top-k most semantically similar KB entries to a query string.
     * Filters by region and is_active. Cross-region entries are always excluded.
     *
     * @param  string $query   The customer message or paraphrase to match against
     * @param  string $region  'africa' or 'europe'
     * @param  int    $topK    Number of results to return (default 8)
     * @return array[]         Array of ['id', 'doc_type', 'product_category', 'content', 'similarity']
     */
    public function findSimilar(string $query, string $region, int $topK = 8): array
    {
        $queryVector = $this->embed($query);
        $driver      = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $vectorLiteral = '[' . implode(',', $queryVector) . ']';

            // Cosine similarity: 1 - cosine_distance. Higher = more similar.
            // <=> is pgvector's cosine distance operator.
            $rows = DB::select(
                'SELECT id, doc_type, product_category, content,
                        1 - (embedding <=> ?) AS similarity
                 FROM knowledge_base
                 WHERE region = ?
                   AND is_active = true
                   AND embedding IS NOT NULL
                 ORDER BY embedding <=> ?
                 LIMIT ?',
                [$vectorLiteral, $region, $vectorLiteral, $topK]
            );

            return array_map(fn ($row) => [
                'id'               => $row->id,
                'doc_type'         => $row->doc_type,
                'product_category' => $row->product_category,
                'content'          => $row->content,
                'similarity'       => (float) $row->similarity,
            ], $rows);
        } else {
            // PHP Cosine Similarity Fallback for SQLite testing / non-backfilled environments
            $records = KnowledgeBase::where('region', $region)
                ->where('is_active', true)
                ->whereNotNull('embedding')
                ->get();

            $results = [];
            foreach ($records as $kb) {
                $embStr = trim($kb->embedding, '[]');
                if (empty($embStr)) {
                    continue;
                }
                $vector = array_map('floatval', explode(',', $embStr));

                // Cosine similarity = dot_product(A, B) / (norm(A) * norm(B))
                $dotProduct = 0.0;
                $normA      = 0.0;
                $normB      = 0.0;
                $len        = min(count($queryVector), count($vector));

                for ($i = 0; $i < $len; $i++) {
                    $dotProduct += $queryVector[$i] * $vector[$i];
                    $normA      += $queryVector[$i] * $queryVector[$i];
                    $normB      += $vector[$i] * $vector[$i];
                }

                $normA = sqrt($normA);
                $normB = sqrt($normB);

                $similarity = ($normA * $normB) > 0 ? ($dotProduct / ($normA * $normB)) : 0.0;

                $results[] = [
                    'id'               => $kb->id,
                    'doc_type'         => $kb->doc_type,
                    'product_category' => $kb->product_category,
                    'content'          => $kb->content,
                    'similarity'       => $similarity,
                ];
            }

            usort($results, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

            return array_slice($results, 0, $topK);
        }
    }
}
