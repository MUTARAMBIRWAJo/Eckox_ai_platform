<?php

namespace App\Services\AI\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * ResponseCache — short-TTL response cache to prevent redundant LLM generations.
 */
class ResponseCache
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = config('llm.cache.response_ttl', 60);
    }

    public function get(array $messages, array $tools): ?array
    {
        $key = $this->key($messages, $tools);
        return Cache::get($key);
    }

    public function put(array $messages, array $tools, array $response): void
    {
        $key = $this->key($messages, $tools);
        Cache::put($key, $response, $this->ttl);
    }

    private function key(array $messages, array $tools): string
    {
        $serialized = json_encode([$messages, $tools]);
        return 'llm_resp:' . hash('sha256', $serialized);
    }
}
