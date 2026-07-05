<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AgentState;

interface LLMProviderClient
{
    /**
     * Generate completion with messages, tools, and state context.
     * Returns a normalized response array containing choice:
     * [
     *   'provider' => string,
     *   'choice' => [
     *      'finish_reason' => string,
     *      'message' => [
     *          'role' => 'assistant',
     *          'content' => ?string,
     *          'tool_calls' => array,
     *      ]
     *   ]
     * ]
     */
    public function generate(array $messages, array $tools, AgentState $state): ?array;
}
