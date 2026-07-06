<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\Memory\ConversationMemory;

class MemoryLoaderNode implements AgentNode
{
    public function __construct(
        private readonly ConversationMemory $conversationMemory
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'memory_loader';
            $state->latencyMs['memory_loader'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // 1. Short-term Memory: load raw message history scrubbed of PII using the sliding window
        if ($state->lead) {
            $state->history = $this->conversationMemory->load($state);
        }

        // 2. Long-term Memory: load summaries & behavioral notes from database
        if ($state->lead) {
            $state->promptPayload['long_term_memories'] = $this->conversationMemory->loadLongTermMemories($state->lead->id);
        } else {
            $state->promptPayload['long_term_memories'] = [];
        }

        $state->nodePath[] = 'memory_loader';
        $state->latencyMs['memory_loader'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }
}
