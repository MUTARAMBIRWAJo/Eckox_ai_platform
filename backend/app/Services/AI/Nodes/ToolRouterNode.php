<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\AgentToolService;
use Illuminate\Support\Facades\Log;

class ToolRouterNode implements AgentNode
{
    public function __construct(
        private readonly AgentToolService $agentTools
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'tool_router';
            $state->latencyMs['tool_router'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // We process all requested tool calls in the state
        // If there are none, we skip
        if (empty($state->toolCalls)) {
            $state->nodePath[] = 'tool_router';
            $state->latencyMs['tool_router'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        $processedCalls = [];

        foreach ($state->toolCalls as &$toolCall) {
            // Check if already executed/staged
            if (isset($toolCall['status']) && $toolCall['status'] !== 'requested') {
                $processedCalls[] = $toolCall;
                continue;
            }

            $name = $toolCall['name'];
            $inputs = $toolCall['arguments'] ?? [];

            // 1. RBAC Context Authorization
            $isCustomerFacing = empty($state->promptPayload['is_internal_staff']) && !empty($state->lead);

            if ($isCustomerFacing) {
                // Deny internal staff actions
                if (in_array($name, ['generate_invoice', 'schedule_followup'])) {
                    $toolCall['status'] = 'rejected';
                    $toolCall['result'] = [
                        'success' => false,
                        'message' => "Access denied: Tool [{$name}] is restricted to internal staff only.",
                    ];
                    $processedCalls[] = $toolCall;
                    continue;
                }
            }

            // 2. Execution vs Staging routing
            // Read-only tools are executed immediately.
            // Escalation is executed immediately.
            // Side-effect actions are staged to execute in Node 7 (after Guardrails in Node 6).
            $isReadOnly = in_array($name, [
                'get_product_price',
                'check_stock',
                'get_product_spec',
                'get_compliance_doc'
            ]);

            if ($isReadOnly || $name === 'escalate_to_human') {
                try {
                    $result = $this->agentTools->dispatch($name, $inputs, $state->traceId);
                    $toolCall['status'] = 'executed';
                    $toolCall['result'] = $result;

                    if ($name === 'escalate_to_human') {
                        $state->escalated = true;
                        $state->reason = $inputs['reason'] ?? 'Escalated to human';
                    }
                } catch (\Throwable $e) {
                    $toolCall['status'] = 'failed';
                    $toolCall['result'] = ['error' => $e->getMessage()];
                }
            } else {
                // Stage the side-effect action for Node 7 (Action Execution)
                $toolCall['status'] = 'staged';
                $toolCall['result'] = [
                    'status'  => 'staged',
                    'message' => "Action [{$name}] staged successfully. Will execute after guardrail security checks.",
                ];
            }

            $processedCalls[] = $toolCall;
        }

        $state->toolCalls = $processedCalls;

        $state->nodePath[] = 'tool_router';
        $state->latencyMs['tool_router'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }
}
