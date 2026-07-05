<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use Illuminate\Support\Facades\Http;

class GroqProvider implements LLMProviderClient
{
    public function generate(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);
        try {
            $apiKey = config('services.groq.key') ?: env('GROQ_API_KEY');
            if (empty($apiKey)) {
                throw new \RuntimeException('Groq API key not configured.');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama3-70b-8192',
                'messages'    => $messages,
                'temperature' => 0.1,
                'max_tokens'  => 800,
                'tools'       => $tools,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Groq API returned error: ' . $response->body());
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? [];
            if (empty($choice)) {
                throw new \RuntimeException('Groq API returned empty choice list.');
            }

            $toolCalls = [];
            if (!empty($choice['message']['tool_calls'])) {
                $toolCalls = array_map(fn ($tc) => [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $tc['function']['name'],
                        'arguments' => $tc['function']['arguments'],
                    ]
                ], $choice['message']['tool_calls']);
            }

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $state->latencyMs['groq_api_call'] = $latency;

            return [
                'provider' => 'groq',
                'choice'   => [
                    'finish_reason' => $choice['finish_reason'] ?? 'stop',
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $choice['message']['content'] ?? null,
                        'tool_calls' => $toolCalls,
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Groq Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }
}
