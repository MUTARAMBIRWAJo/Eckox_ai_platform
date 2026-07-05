<?php

namespace App\Services\AI\Nodes;

use App\Models\AiMemory;
use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;

class MemoryLoaderNode implements AgentNode
{
    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'memory_loader';
            $state->latencyMs['memory_loader'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // 1. Short-term Memory: load raw message history scrubbed of PII
        if ($state->lead) {
            $state->history = $state->lead->inboundMessages()
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($m) => [
                    'role'    => 'user',
                    'content' => $this->redactPII($m->content),
                ])
                ->toArray();
        }

        // 2. Long-term Memory: load summaries, behavioral notes, and products discussed
        if ($state->lead) {
            $memories = AiMemory::where('lead_id', $state->lead->id)->get();
            $state->promptPayload['long_term_memories'] = $memories->map(fn ($m) => [
                'type'    => $m->memory_type,
                'content' => $this->redactPII($m->content),
            ])->toArray();
        } else {
            $state->promptPayload['long_term_memories'] = [];
        }

        $state->nodePath[] = 'memory_loader';
        $state->latencyMs['memory_loader'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    private function redactPII(string $text): string
    {
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        $text = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $text);
        return $text;
    }
}
