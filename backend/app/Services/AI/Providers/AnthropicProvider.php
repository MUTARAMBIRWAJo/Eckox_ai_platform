<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LLMProviderClient
{
    public function generate(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);
        try {
            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (empty($apiKey)) {
                throw new \RuntimeException('Anthropic API key not configured.');
            }

            $system = collect($messages)->first(fn ($m) => $m['role'] === 'system')['content'] ?? '';
            $chatMessages = collect($messages)->filter(fn ($m) => $m['role'] !== 'system')->values()->toArray();

            // Convert OpenAI tools to Anthropic format
            $anthropicTools = $this->buildAnthropicTools($tools);

            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 1024,
                'system'     => $system,
                'messages'   => $this->mapMessagesToAnthropic($chatMessages),
                'tools'      => $anthropicTools,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Anthropic API returned error: ' . $response->body());
            }

            $data = $response->json();
            $contentBlocks = $data['content'] ?? [];
            $text = '';
            $toolCalls = [];

            foreach ($contentBlocks as $block) {
                if ($block['type'] === 'text') {
                    $text .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $block['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $block['name'],
                            'arguments' => json_encode($block['input']),
                        ]
                    ];
                }
            }

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $state->latencyMs['anthropic_api_call'] = $latency;

            return [
                'provider' => 'anthropic',
                'choice'   => [
                    'finish_reason' => empty($toolCalls) ? 'stop' : 'tool_calls',
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $text,
                        'tool_calls' => $toolCalls,
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Anthropic Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buildAnthropicTools(array $openAiTools): array
    {
        return array_map(function ($tool) {
            $fn = $tool['function'] ?? [];
            $params = $fn['parameters'] ?? [];
            return [
                'name'        => $fn['name'] ?? '',
                'description' => $fn['description'] ?? '',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => $params['properties'] ?? [],
                    'required'   => $params['required'] ?? [],
                ]
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
                        ]
                    ]
                ];
            }
            return [
                'role'    => $role,
                'content' => $m['content'] ?? '',
            ];
        }, $messages);
    }
}
