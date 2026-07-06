<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use App\Services\AI\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements LLMProviderInterface
{
    private const API_BASE    = 'https://api.anthropic.com/v1';
    private const API_VERSION = '2023-06-01';

    // ─── LLMProviderInterface ─────────────────────────────────────────────────

    public function name(): string { return 'anthropic'; }

    public function model(): string
    {
        return config('llm.models.anthropic.chat', 'claude-3-5-sonnet-20241022');
    }

    public function supportsVision(): bool { return true; }

    public function supportsTools(): bool { return true; }

    // ─── chat() ───────────────────────────────────────────────────────────────

    public function chat(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);

        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured.');
        }

        $system       = collect($messages)->first(fn ($m) => $m['role'] === 'system')['content'] ?? '';
        $chatMessages = collect($messages)->filter(fn ($m) => $m['role'] !== 'system')->values()->toArray();

        $payload = [
            'model'      => $this->model(),
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $this->mapMessagesToAnthropic($chatMessages),
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->buildAnthropicTools($tools);
        }

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type'      => 'application/json',
        ])->timeout(config('llm.timeout', 30))->post(self::API_BASE . '/messages', $payload);

        if (!$response->successful()) {
            // Log at debug level only — body may contain provider error details but not our keys
            Log::channel('production')->debug('Anthropic API error response', [
                'status'   => $response->status(),
                'trace_id' => $state->traceId,
            ]);
            throw new \RuntimeException(
                'Anthropic API error ' . $response->status() . ': ' . $this->sanitiseErrorBody($response->body())
            );
        }

        $data          = $response->json();
        $contentBlocks = $data['content'] ?? [];
        $text          = '';
        $toolCalls     = [];

        foreach ($contentBlocks as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id'       => $block['id'],
                    'type'     => 'function',
                    'function' => [
                        'name'      => $block['name'],
                        'arguments' => json_encode($block['input']),
                    ],
                ];
            }
        }

        $usage            = $data['usage'] ?? [];
        $promptTokens     = $usage['input_tokens'] ?? 0;
        $completionTokens = $usage['output_tokens'] ?? 0;
        $latency          = (int) round((microtime(true) - $startedAt) * 1000);

        $state->latencyMs['anthropic_api_call'] = $latency;

        return [
            'provider' => 'anthropic',
            'model'    => $this->model(),
            'choice'   => [
                'finish_reason' => empty($toolCalls) ? 'stop' : 'tool_calls',
                'message'       => [
                    'role'       => 'assistant',
                    'content'    => $text,
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
            throw new \RuntimeException('Anthropic Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── stream() ─────────────────────────────────────────────────────────────

    public function stream(array $messages, AgentState $state): \Generator
    {
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured.');
        }

        $system       = collect($messages)->first(fn ($m) => $m['role'] === 'system')['content'] ?? '';
        $chatMessages = collect($messages)->filter(fn ($m) => $m['role'] !== 'system')->values()->toArray();

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type'      => 'application/json',
        ])->timeout(config('llm.timeout', 30))->post(self::API_BASE . '/messages', [
            'model'      => $this->model(),
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $this->mapMessagesToAnthropic($chatMessages),
            'stream'     => true,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Anthropic API stream error ' . $response->status() . ': ' . $this->sanitiseErrorBody($response->body())
            );
        }

        // Parse SSE stream from Anthropic
        foreach (explode("\n", $response->body()) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $data = json_decode(substr($line, 6), true);
                if (isset($data['delta']['text'])) {
                    yield $data['delta']['text'];
                }
            }
        }
    }

    // ─── embeddings() ─────────────────────────────────────────────────────────

    public function embeddings(string $text): array
    {
        // Anthropic does not currently provide an embeddings API.
        // Delegate to OpenAI embeddings via the injected provider.
        throw new \RuntimeException('Anthropic does not provide an embeddings API. Use OpenAIProvider::embeddings() instead.');
    }

    // ─── health() ─────────────────────────────────────────────────────────────

    public function health(): array
    {
        $start  = microtime(true);
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            return ['healthy' => false, 'latency_ms' => 0, 'error' => 'API key not configured'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])->timeout(10)->post(self::API_BASE . '/messages', [
                'model'      => $this->model(),
                'max_tokens' => 1,
                'messages'   => [['role' => 'user', 'content' => 'ping']],
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
        $pricing = config("llm.pricing.anthropic.{$model}", ['input' => 3.000, 'output' => 15.000]);

        return round(
            ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000,
            8
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function buildAnthropicTools(array $openAiTools): array
    {
        return array_map(function ($tool) {
            $fn     = $tool['function'] ?? [];
            $params = $fn['parameters'] ?? [];
            return [
                'name'         => $fn['name'] ?? '',
                'description'  => $fn['description'] ?? '',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => $params['properties'] ?? [],
                    'required'   => $params['required'] ?? [],
                ],
            ];
        }, $openAiTools);
    }

    private function mapMessagesToAnthropic(array $messages): array
    {
        return array_map(function ($m) {
            $role = $m['role'];
            if ($role === 'system') {
                $role = 'user';
            }
            if ($role === 'tool') {
                return [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Tool response for call {$m['tool_call_id']}: {$m['content']}",
                        ],
                    ],
                ];
            }
            return [
                'role'    => $role,
                'content' => $m['content'] ?? '',
            ];
        }, $messages);
    }

    /**
     * Strip any potential credential-shaped strings from error body before logging.
     */
    private function sanitiseErrorBody(string $body): string
    {
        // Remove anything that looks like a key (sk-, gsk_, ghp_, Bearer tokens)
        $sanitised = preg_replace('/(sk-[A-Za-z0-9\-_]{10,}|gsk_[A-Za-z0-9]{10,}|ghp_[A-Za-z0-9]{10,}|Bearer\s+\S+)/i', '[REDACTED]', $body);
        return mb_substr($sanitised, 0, 500); // Cap body length in error messages
    }
}
