<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\AgentState;

/**
 * LLMProviderInterface — canonical contract for all LLM provider implementations.
 *
 * All three providers (OpenAI, Anthropic, Groq) implement this interface.
 * The legacy generate() method is preserved as an alias for chat() so existing
 * tests and LlmReasoningNode callers are unaffected.
 */
interface LLMProviderInterface
{
    /**
     * Generate a chat completion.
     * Returns a normalised response array:
     * [
     *   'provider' => string,
     *   'model'    => string,
     *   'choice'   => [
     *     'finish_reason' => string,
     *     'message' => [
     *       'role'       => 'assistant',
     *       'content'    => ?string,
     *       'tool_calls' => array,
     *     ],
     *   ],
     *   'usage' => [
     *     'prompt_tokens'     => int,
     *     'completion_tokens' => int,
     *     'total_tokens'      => int,
     *   ],
     *   'cost_usd' => float,
     *   'latency_ms' => int,
     * ]
     */
    public function chat(array $messages, array $tools, AgentState $state): ?array;

    /**
     * Legacy alias for chat() — preserves backward compat with LlmReasoningNode.
     */
    public function generate(array $messages, array $tools, AgentState $state): ?array;

    /**
     * Stream a chat completion, yielding string chunks as they arrive.
     *
     * @return \Generator<string>
     */
    public function stream(array $messages, AgentState $state): \Generator;

    /**
     * Generate a text embedding vector for the given input.
     * Returns float[] of the embedding dimensions.
     */
    public function embeddings(string $text): array;

    /**
     * Perform a lightweight health check against the provider's API.
     * Returns ['healthy' => bool, 'latency_ms' => int, 'error' => ?string].
     */
    public function health(): array;

    /**
     * Calculate the USD cost for the given token counts.
     */
    public function cost(int $promptTokens, int $completionTokens): float;

    /**
     * Return the model identifier currently configured for chat.
     */
    public function model(): string;

    /**
     * Whether this provider supports image/vision inputs.
     */
    public function supportsVision(): bool;

    /**
     * Whether this provider supports tool/function calling.
     */
    public function supportsTools(): bool;

    /**
     * Return the canonical provider name: 'openai' | 'anthropic' | 'groq'.
     */
    public function name(): string;
}
