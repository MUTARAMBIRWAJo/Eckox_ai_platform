<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use App\Services\AI\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqProvider implements LLMProviderInterface
{
    // ─── LLMProviderInterface ─────────────────────────────────────────────────

    public function name(): string { return 'groq'; }

    public function model(): string
    {
        return config('llm.models.groq.chat', 'llama-3.3-70b-versatile');
    }

    public function supportsVision(): bool { return false; }

    public function supportsTools(): bool { return true; }

    private function baseUrl(): string
    {
        return config('services.groq.base_url', 'https://api.groq.com/openai/v1');
    }

    // ─── chat() ───────────────────────────────────────────────────────────────

    public function chat(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);

        $apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('Groq API key not configured.');
        }

        $payload = [
            'model'       => $this->model(),
            'messages'    => $messages,
            'temperature' => 0.1,
            'max_tokens'  => 800,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(config('llm.timeout', 30))->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Groq API error ' . $response->status() . ': ' . $this->sanitiseErrorBody($response->body())
            );
        }

        $data   = $response->json();
        $choice = $data['choices'][0] ?? [];

        if (empty($choice)) {
            throw new \RuntimeException('Groq API returned empty choice list.');
        }

        $toolCalls = [];
        if (!empty($choice['message']['tool_calls'])) {
            $toolCalls = array_map(fn ($tc) => [
                'id'       => $tc['id'],
                'type'     => 'function',
                'function' => [
                    'name'      => $tc['function']['name'],
                    'arguments' => $tc['function']['arguments'],
                ],
            ], $choice['message']['tool_calls']);
        }

        $usage            = $data['usage'] ?? [];
        $promptTokens     = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        $latency          = (int) round((microtime(true) - $startedAt) * 1000);

        $state->latencyMs['groq_api_call'] = $latency;

        return [
            'provider' => 'groq',
            'model'    => $this->model(),
            'choice'   => [
                'finish_reason' => $choice['finish_reason'] ?? 'stop',
                'message'       => [
                    'role'       => 'assistant',
                    'content'    => $choice['message']['content'] ?? null,
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
     * Legacy alias — preserves backward compat with LlmReasoningNode.
     */
    public function generate(array $messages, array $tools, AgentState $state): ?array
    {
        try {
            return $this->chat($messages, $tools, $state);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Groq Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── stream() ─────────────────────────────────────────────────────────────

    public function stream(array $messages, AgentState $state): \Generator
    {
        $apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('Groq API key not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(config('llm.timeout', 30))->post($this->baseUrl() . '/chat/completions', [
            'model'       => $this->model(),
            'messages'    => $messages,
            'temperature' => 0.1,
            'max_tokens'  => 800,
            'stream'      => true,
        ]);

        foreach (explode("\n", $response->body()) as $line) {
            if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                $data  = json_decode(substr($line, 6), true);
                $delta = $data['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    // ─── embeddings() ─────────────────────────────────────────────────────────

    public function embeddings(string $text): array
    {
        // Groq does not currently provide an embeddings API.
        throw new \RuntimeException('Groq does not provide an embeddings API. Use OpenAIProvider::embeddings() instead.');
    }

    // ─── health() ─────────────────────────────────────────────────────────────

    public function health(): array
    {
        $start  = microtime(true);
        $apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');

        if (empty($apiKey)) {
            return ['healthy' => false, 'latency_ms' => 0, 'error' => 'API key not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(10)->post($this->baseUrl() . '/chat/completions', [
                'model'      => $this->model(),
                'messages'   => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ]);

            return [
                'healthy'    => $response->successful(),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error'      => $response->successful() ? null : 'HTTP ' . $response->status(),
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
        $pricing = config("llm.pricing.groq.{$model}", ['input' => 0.590, 'output' => 0.790]);

        return round(
            ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000,
            8
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function sanitiseErrorBody(string $body): string
    {
        $sanitised = preg_replace('/(sk-[A-Za-z0-9\-_]{10,}|gsk_[A-Za-z0-9]{10,}|Bearer\s+\S+)/i', '[REDACTED]', $body);
        return mb_substr($sanitised, 0, 500);
    }
}
