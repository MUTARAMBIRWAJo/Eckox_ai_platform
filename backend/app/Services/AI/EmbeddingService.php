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
    public const ACTIVE_MODEL = 'bge-small-en-v1.5-padded';

    public function __construct(private readonly EmbeddingCache $cache) {}

    /**
     * Return the configured embedding model name.
     */
    public function getModel(): string
    {
        return config('llm.models.openai.embedding', 'text-embedding-3-small');
    }

    public function embed(string $text): array
    {
        // Cache hit?
        $cached = $this->cache->get($text);
        if ($cached !== null) {
            return $cached;
        }

        $token = env('HF_TOKEN');
        if (empty($token)) {
            throw new \Exception("Hugging Face API token (HF_TOKEN) is not configured in environment.");
        }

        $ch = curl_init('https://router.huggingface.co/hf-inference/models/BAAI/bge-small-en-v1.5');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'inputs' => $text,
                'options' => ['wait_for_model' => true]
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        
        $res = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $vector = json_decode($res, true);
        
        if ($status !== 200 || !is_array($vector)) {
            $errorMsg = is_array($vector) && isset($vector['error']) ? $vector['error'] : $res;
            throw new \Exception("Hugging Face Embedding API failed with status $status: $errorMsg");
        }

        // Pad the 384-dimensional vector to 1536 dimensions to match pgvector schema
        $padded = array_pad($vector, self::DIMENSIONS, 0.0);
        
        // Normalize the padded vector to unit length
        $sumSq = array_sum(array_map(fn($x) => $x * $x, $padded));
        $norm = sqrt($sumSq) ?: 1.0;
        $normalizedVector = array_map(fn($x) => $x / $norm, $padded);

        // Store in cache
        $this->cache->put($text, $normalizedVector);

        return $normalizedVector;
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
            ->update([
                'embedding' => '[' . implode(',', $vector) . ']',
                'embedding_model' => self::ACTIVE_MODEL
            ]);

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
                   AND embedding_model = ?
                 ORDER BY embedding <=> ?
                 LIMIT ?',
                [$vectorLiteral, $region, self::ACTIVE_MODEL, $vectorLiteral, $topK]
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
                ->where('embedding_model', self::ACTIVE_MODEL)
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
