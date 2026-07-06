<?php

namespace App\Services\AI\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * ConversationCache — Redis cache wrapper for sliding conversation window.
 */
class ConversationCache
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = config('llm.memory.cache_ttl', 86400);
    }

    public function get(int $leadId): ?array
    {
        return Cache::get($this->key($leadId));
    }

    public function put(int $leadId, array $history): void
    {
        Cache::put($this->key($leadId), $history, $this->ttl);
    }

    public function forget(int $leadId): void
    {
        Cache::forget($this->key($leadId));
    }

    private function key(int $leadId): string
    {
        return "conv_mem_cache:{$leadId}";
    }
}
