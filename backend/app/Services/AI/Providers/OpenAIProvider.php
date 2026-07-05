<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements LLMProviderClient
{
    public function generate(array $messages, array $tools, AgentState $state): ?array
    {
        $startedAt = microtime(true);
        try {
            $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                throw new \RuntimeException('OpenAI API key not configured.');
            }

            $response = OpenAI::chat()->create([
                'model'       => config('openai.model', 'gpt-4o-mini'),
                'messages'    => $messages,
                'temperature' => 0.1,
                'max_tokens'  => 800,
                'tools'       => $tools,
                'tool_choice' => 'auto',
            ]);

            $choice = $response->choices[0];
            $toolCalls = [];
            if (!empty($choice->message->toolCalls)) {
                $toolCalls = array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->function->name,
                        'arguments' => $tc->function->arguments,
                    ]
                ], $choice->message->toolCalls);
            }

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $state->latencyMs['openai_api_call'] = $latency;

            return [
                'provider' => 'openai',
                'choice'   => [
                    'finish_reason' => $choice->finishReason ?? 'stop',
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $choice->message->content,
                        'tool_calls' => $toolCalls,
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('OpenAI Provider execution error: ' . $e->getMessage(), 0, $e);
        }
    }
}
