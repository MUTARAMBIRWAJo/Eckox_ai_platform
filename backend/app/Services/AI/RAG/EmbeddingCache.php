<?php

namespace App\Services\AI\RAG;

use Illuminate\Support\Facades\Cache;

/**
 * EmbeddingCache — Redis-backed cache for OpenAI embedding vectors.
 *
 * Avoids repeated API calls for identical or near-identical text inputs.
 * TTL default: 7 days (configurable via config/llm.php cache.embeddings_ttl).
 */
class EmbeddingCache
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = config('llm.cache.embeddings_ttl', 604800);
    }

    /**
     * Attempt to retrieve a cached embedding.
     * Returns float[]|null.
     */
    public function get(string $text): ?array
    {
        $cached = Cache::get($this->key($text));
        if ($cached === null) {
            return null;
        }
        return json_decode($cached, true);
    }

    /**
     * Store an embedding in cache.
     * @param float[] $vector
     */
    public function put(string $text, array $vector): void
    {
        Cache::put($this->key($text), json_encode($vector), $this->ttl);
    }

    /**
     * Remove a cached embedding (e.g. when KB content is updated).
     */
    public function forget(string $text): void
    {
        Cache::forget($this->key($text));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function key(string $text): string
    {
        // SHA-256 of text ensures collision-resistant, fixed-length keys
        return 'emb:' . hash('sha256', $text);
    }
}
