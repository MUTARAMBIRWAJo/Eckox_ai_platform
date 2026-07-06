<?php

namespace App\Services\AI\Nodes;

use App\Models\AiActionsLog;
use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\Guardrails\ConfidenceScorer;
use Illuminate\Support\Facades\Log;

class LoggingObservabilityNode implements AgentNode
{
    public function __construct(private readonly ConfidenceScorer $confidenceScorer) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        $state->nodePath[] = 'logging_observability';

        // Calculate total pipeline latency
        $totalLatencyMs = (int) round(array_sum(array_filter(
            $state->latencyMs,
            fn ($v, $k) => is_numeric($v) && !in_array($k, ['tokens_prompt', 'tokens_completion', 'cost_usd']),
            ARRAY_FILTER_USE_BOTH
        )));

        // Score confidence of final decision
        $confidenceResult = $this->confidenceScorer->score($state->finalDecision);

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
                // Enriched observability
                'provider'          => $state->llmProvider,
                'model_name'        => $state->latencyMs['model'] ?? null,
                'tokens_prompt'     => $state->latencyMs['tokens_prompt'] ?? 0,
                'tokens_completion' => $state->latencyMs['tokens_completion'] ?? 0,
                'cost_usd'          => $state->latencyMs['cost_usd'] ?? 0.0,
                'retries'           => 0, // Retries tracked by LLMRouter; extend if needed
                'fallback_used'     => !in_array($state->llmProvider, [config('llm.default')], true),
                'total_latency_ms'  => $totalLatencyMs,
                'confidence_score'  => $confidenceResult['score'],
                'intent'            => $state->intent,
            ]);
        } catch (\Throwable $e) {
            Log::channel('production')->error('Failed to write ai_actions_log observability entry', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);
        }

        // Structured log for external monitoring (Datadog, Grafana, etc.)
        Log::channel('production')->info('AI pipeline completed', [
            'trace_id'         => $state->traceId,
            'lead_id'          => $state->lead?->id,
            'provider'         => $state->llmProvider,
            'model'            => $state->latencyMs['model'] ?? 'unknown',
            'intent'           => $state->intent,
            'decision'         => $state->finalDecision['decision'] ?? 'reply',
            'escalated'        => $state->escalated,
            'tokens_prompt'    => $state->latencyMs['tokens_prompt'] ?? 0,
            'tokens_completion' => $state->latencyMs['tokens_completion'] ?? 0,
            'cost_usd'         => $state->latencyMs['cost_usd'] ?? 0.0,
            'total_latency_ms' => $totalLatencyMs,
            'confidence'       => $confidenceResult['score'],
            'node_path'        => $state->nodePath,
        ]);

        $state->latencyMs['logging_observability'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }
}
