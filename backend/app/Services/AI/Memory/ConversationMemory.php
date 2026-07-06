<?php

namespace App\Services\AI\Memory;

use App\Models\AiMemory;
use App\Services\AI\AgentState;
use App\Services\AI\Privacy\PIIRedactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ConversationMemory — persistent, Redis-cached conversation context management.
 *
 * Strategy:
 *  1. Load raw message history from DB (most recent N turns — sliding window).
 *  2. Cache the window in Redis for fast subsequent loads.
 *  3. If history exceeds summarize_after turns, generate a summary using the
 *     fastest available LLM (Groq) and store it as an AiMemory record.
 *  4. Never send the full history to the LLM — only the window + any summary.
 */
class ConversationMemory
{
    private int $windowSize;
    private int $cacheTtl;
    private int $summarizeAfter;

    public function __construct(private readonly PIIRedactor $piiRedactor)
    {
        $this->windowSize     = config('llm.memory.window_size', 10);
        $this->cacheTtl       = config('llm.memory.cache_ttl', 86400);
        $this->summarizeAfter = config('llm.memory.summarize_after', 20);
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Load conversation history for the given lead, applying the sliding window.
     * Returns a messages array ready to inject into a prompt.
     */
    public function load(AgentState $state): array
    {
        if (!$state->lead) {
            return [];
        }

        $leadId   = $state->lead->id;
        $cacheKey = $this->cacheKey($leadId);

        // Try Redis cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Load from DB
        $history = $this->loadFromDb($leadId);

        // Cache for TTL
        Cache::put($cacheKey, $history, $this->cacheTtl);

        return $history;
    }

    /**
     * Append a new exchange (user + assistant) to memory, refresh cache.
     */
    public function append(int $leadId, string $userMessage, string $assistantReply, string $provider): void
    {
        // Long-term memory: store as AiMemory record if significant
        AiMemory::create([
            'lead_id'      => $leadId,
            'memory_type'  => 'conversation_turn',
            'content'      => json_encode([
                'user'      => $this->piiRedactor->redact($userMessage),
                'assistant' => $assistantReply,
                'provider'  => $provider,
                'ts'        => now()->toISOString(),
            ]),
        ]);

        // Invalidate cache so next load is fresh
        Cache::forget($this->cacheKey($leadId));

        // Trigger summarization if history is growing large
        $this->maybeSummarize($leadId);
    }

    /**
     * Load long-term memory notes (summaries, behavioural patterns).
     */
    public function loadLongTermMemories(int $leadId): array
    {
        return AiMemory::where('lead_id', $leadId)
            ->where('memory_type', 'summary')
            ->latest()
            ->take(3)
            ->get()
            ->map(fn ($m) => [
                'type'    => $m->memory_type,
                'content' => $m->content,
            ])
            ->toArray();
    }

    /**
     * Invalidate the Redis cache for a lead (e.g. on lead update).
     */
    public function invalidate(int $leadId): void
    {
        Cache::forget($this->cacheKey($leadId));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function loadFromDb(int $leadId): array
    {
        $turns = AiMemory::where('lead_id', $leadId)
            ->where('memory_type', 'conversation_turn')
            ->latest()
            ->take($this->windowSize)
            ->get()
            ->reverse()
            ->values();

        $history = [];

        // Load any existing summary first
        $summary = AiMemory::where('lead_id', $leadId)
            ->where('memory_type', 'summary')
            ->latest()
            ->first();

        if ($summary) {
            $history[] = [
                'role'    => 'system',
                'content' => '[CONVERSATION SUMMARY]: ' . $summary->content,
            ];
        }

        foreach ($turns as $turn) {
            $data = json_decode($turn->content, true) ?? [];
            if (!empty($data['user'])) {
                $history[] = ['role' => 'user',      'content' => $data['user']];
            }
            if (!empty($data['assistant'])) {
                $history[] = ['role' => 'assistant', 'content' => $data['assistant']];
            }
        }

        return $history;
    }

    /**
     * Trigger async summarization when turn count exceeds the threshold.
     * Uses Groq (fastest/cheapest) for summarization.
     */
    private function maybeSummarize(int $leadId): void
    {
        $turnCount = AiMemory::where('lead_id', $leadId)
            ->where('memory_type', 'conversation_turn')
            ->count();

        if ($turnCount < $this->summarizeAfter) {
            return;
        }

        try {
            $turns = AiMemory::where('lead_id', $leadId)
                ->where('memory_type', 'conversation_turn')
                ->oldest()
                ->take($this->summarizeAfter)
                ->get();

            $dialogue = $turns->map(function ($t) {
                $d = json_decode($t->content, true) ?? [];
                return "User: " . ($d['user'] ?? '') . "\nAgent: " . ($d['assistant'] ?? '');
            })->implode("\n\n");

            // Use Groq for cheap, fast summarization
            $groq = app(\App\Services\AI\Providers\GroqProvider::class);
            // Create a minimal dummy state for the call
            $dummyState = new \App\Services\AI\AgentState('summary-' . $leadId);
            $result = $groq->chat([
                ['role' => 'system', 'content' => 'You are a conversation summarizer. Summarize the key facts from this B2B sales conversation in 3-5 bullet points. Focus on: products discussed, prices mentioned, concerns raised, decisions made.'],
                ['role' => 'user', 'content' => $dialogue],
            ], [], $dummyState);

            $summaryText = $result['choice']['message']['content'] ?? '';

            if ($summaryText) {
                AiMemory::create([
                    'lead_id'     => $leadId,
                    'memory_type' => 'summary',
                    'content'     => $summaryText,
                ]);

                // Archive summarized turns
                $ids = $turns->pluck('id');
                AiMemory::whereIn('id', $ids)->update(['memory_type' => 'archived_turn']);

                Cache::forget($this->cacheKey($leadId));

                Log::channel('production')->info('ConversationMemory: generated summary', [
                    'lead_id'      => $leadId,
                    'turns_summed' => $ids->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('production')->warning('ConversationMemory: summarization failed', [
                'lead_id' => $leadId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function cacheKey(int $leadId): string
    {
        return "conv_memory:{$leadId}";
    }
}
