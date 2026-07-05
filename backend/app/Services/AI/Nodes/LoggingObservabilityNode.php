<?php

namespace App\Services\AI\Nodes;

use App\Models\AiActionsLog;
use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use Illuminate\Support\Facades\Log;

class LoggingObservabilityNode implements AgentNode
{
    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        $state->nodePath[] = 'logging_observability';
        $state->latencyMs['logging_observability'] = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            AiActionsLog::create([
                'trace_id'          => $state->traceId,
                'lead_id'           => $state->lead?->id,
                'node_path'         => $state->nodePath,
                'latency_ms'        => $state->latencyMs,
                'llm_provider'      => $state->llmProvider,
                'tool_calls'        => $state->toolCalls,
                'guardrail_verdict' => $state->guardrailVerdict ?? ['error' => $state->reason],
                'decision_type'     => $state->finalDecision['decision'] ?? 'reply',
                'action_executed'   => $state->actionsTaken,
            ]);
        } catch (\Throwable $e) {
            Log::channel('production')->error('Failed to write ai_actions_log observability entry', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);
        }

        return $state;
    }
}
