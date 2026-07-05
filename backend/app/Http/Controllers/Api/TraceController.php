<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiActionsLog;
use Illuminate\Http\JsonResponse;

class TraceController extends Controller
{
    public function show(string $traceId): JsonResponse
    {
        $log = AiActionsLog::where('trace_id', $traceId)
            ->with(['lead'])
            ->firstOrFail();

        // Calculate if failover or retry cycle happened
        // Failover: if the LLM provider cast contains warnings or fallback executions
        // Retry cycle: if guardrail_verdict contains error messages or count > 0
        $hasFailover = false;
        $hasRetryCycle = false;

        if (isset($log->guardrail_verdict['errors']) && count($log->guardrail_verdict['errors']) > 0) {
            $hasRetryCycle = true;
        }

        // We can inspect node_path and latency_ms to see if retries ran
        if (is_array($log->node_path) && count(array_keys($log->node_path, 'guardrail_validation')) > 1) {
            $hasRetryCycle = true;
        }

        // If tool_calls contains mock provider logs or errors, failover was active
        if ($log->llm_provider !== 'openai') {
            $hasFailover = true;
        }

        return response()->json([
            'id' => $log->id,
            'traceId' => $log->trace_id,
            'leadId' => $log->lead_id,
            'leadName' => $log->lead?->name ?? 'Unknown Client',
            'nodePath' => $log->node_path ?? [],
            'latencyMs' => $log->latency_ms ?? [],
            'llmProvider' => $log->llm_provider ?? 'openai',
            'toolCalls' => $log->tool_calls ?? [],
            'guardrailVerdict' => $log->guardrail_verdict,
            'decisionType' => $log->decision_type ?? 'reply',
            'actionExecuted' => $log->action_executed,
            'createdAt' => $log->created_at->toIso8601String(),
            'hasFailover' => $hasFailover,
            'hasRetryCycle' => $hasRetryCycle,
        ]);
    }
}
