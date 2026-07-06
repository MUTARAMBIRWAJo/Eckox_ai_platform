<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use App\Services\AI\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements LLMProviderInterface
{
    // ─── LLMProviderInterface ─────────────────────────────────────────────────

    public function name(): string { return 'openai'; }

    public function model(): string
    {
        return config('llm.models.openai.chat', 'gpt-4o-mini');
    }

    public function supportsVision(): bool { return true; }

    public function supportsTools(): bool { return true; }

    // ─── chat() — canonical completion ────────────────────────────────────────

    public function chat(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);

        $apiKey = config('openai.api_key') ?: config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured.');
        }

        $response = OpenAI::chat()->create([
            'model'       => $this->model(),
            'messages'    => $messages,
            'temperature' => 0.1,
            'max_tokens'  => 800,
            'tools'       => $tools ?: null,
            'tool_choice' => $tools ? 'auto' : null,
        ]);

        $choice = $response->choices[0];

        $toolCalls = [];
        if (!empty($choice->message->toolCalls)) {
            $toolCalls = array_map(fn ($tc) => [
                'id'       => $tc->id,
                'type'     => 'function',
                'function' => [
                    'name'      => $tc->function->name,
                    'arguments' => $tc->function->arguments,
                ],
            ], $choice->message->toolCalls);
        }

        $usage = $response->usage ?? null;
        $promptTokens     = $usage?->promptTokens ?? 0;
        $completionTokens = $usage?->completionTokens ?? 0;
        $latency          = (int) round((microtime(true) - $startedAt) * 1000);

        $state->latencyMs['openai_api_call'] = $latency;

        return [
            'provider'   => 'openai',
            'model'      => $this->model(),
            'choice'     => [
                'finish_reason' => $choice->finishReason ?? 'stop',
                'message'       => [
                    'role'       => 'assistant',
                    'content'    => $choice->message->content,
                    'tool_calls' => $toolCalls,
                ],
            ],
            'usage' => [
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $promptTokens + $completionTokens,
            ],
            'cost_usd'   => $this->cost($promptTokens, $completionTokens),
            'latency_ms' => $latency,
        ];
    }

    /**
     * Legacy alias — preserves compatibility with LlmReasoningNode callers.
     */
    public function generate(array $messages, array $tools, AgentState $state): ?array
    {
        try {
            return $this->chat($messages, $tools, $state);
        } catch (\Throwable $e) {
            throw new \RuntimeException('OpenAI Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── stream() ─────────────────────────────────────────────────────────────

    public function stream(array $messages, AgentState $state): \Generator
    {
        $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured.');
        }

        $stream = OpenAI::chat()->createStreamed([
            'model'       => $this->model(),
            'messages'    => $messages,
            'temperature' => 0.1,
            'max_tokens'  => 800,
        ]);

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta->content ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    // ─── embeddings() ─────────────────────────────────────────────────────────

    public function embeddings(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => config('llm.models.openai.embedding', 'text-embedding-3-small'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    // ─── health() ─────────────────────────────────────────────────────────────

    public function health(): array
    {
        $start = microtime(true);
        try {
            $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                return ['healthy' => false, 'latency_ms' => 0, 'error' => 'API key not configured'];
            }

            OpenAI::chat()->create([
                'model'      => $this->model(),
                'messages'   => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ]);

            return [
                'healthy'    => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy'    => false,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error'      => $e->getMessage(),
            ];
        }
    }

    // ─── cost() ───────────────────────────────────────────────────────────────

    public function cost(int $promptTokens, int $completionTokens): float
    {
        $model   = $this->model();
        $pricing = config("llm.pricing.openai.{$model}", ['input' => 0.150, 'output' => 0.600]);

        // Pricing is per 1M tokens
        return round(
            ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000,
            8
        );
    }
}
