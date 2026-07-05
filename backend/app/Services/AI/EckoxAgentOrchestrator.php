<?php

namespace App\Services\AI;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\AiDecision;
use App\Services\AI\Nodes\IntentClassifierNode;
use App\Services\AI\Nodes\MemoryLoaderNode;
use App\Services\AI\Nodes\RagRetrievalNode;
use App\Services\AI\Nodes\LlmReasoningNode;
use App\Services\AI\Nodes\GuardrailValidationNode;
use App\Services\AI\Nodes\ActionExecutionNode;
use App\Services\AI\Nodes\LoggingObservabilityNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EckoxAgentOrchestrator
{
    private array $nodes = [];

    public function __construct(
        private readonly IntentClassifierNode    $intentNode,
        private readonly MemoryLoaderNode        $memoryNode,
        private readonly RagRetrievalNode        $ragNode,
        private readonly LlmReasoningNode        $reasoningNode,
        private readonly GuardrailValidationNode $guardrailNode,
        private readonly ActionExecutionNode     $actionNode,
        private readonly LoggingObservabilityNode $observabilityNode
    ) {
        // Register nodes in correct architectural sequence
        $this->nodes = [
            'intent_classifier'    => $this->intentNode,
            'memory_loader'        => $this->memoryNode,
            'rag_retrieval'        => $this->ragNode,
            'llm_reasoning'        => $this->reasoningNode,
            'guardrail_validation' => $this->guardrailNode,
            'action_execution'     => $this->actionNode,
            'logging_observability' => $this->observabilityNode,
        ];
    }

    /**
     * Main entry point to run the agent graph for an inbound message.
     */
    public function run(InboundMessage $message, ?Lead $lead = null, ?string $traceId = null): AgentState
    {
        $traceId = $traceId ?: (string) Str::uuid();

        // 1. Idempotency check: Load existing state from DB if this is a job retry
        $state = $this->loadState($traceId);
        if (!$state) {
            $state = new AgentState($traceId);
            $state->message = $message;
            $state->lead = $lead;
            $this->saveState($state);
        } else {
            $state->isRetry = true;
            // Re-bind message/lead in case of serialization issues
            $state->message = $message;
            $state->lead = $lead;
        }

        // Execute graph sequence
        foreach ($this->nodes as $name => $node) {
            // Idempotency: Skip already completed steps if this is a retried run
            if (in_array($name, $state->completedSteps)) {
                Log::channel('production')->info("Skipping already completed node in retry run", [
                    'node'     => $name,
                    'trace_id' => $traceId,
                ]);
                continue;
            }

            try {
                $state = $node->handle($state);
                $state->completedSteps[] = $name;
                // Handle guardrail validation failure retry loop (Factual validation mismatch)
                if ($name === 'guardrail_validation' && $state->guardrailFailed && !$state->isRetry) {
                    Log::channel('production')->warning('First guardrail check failed, triggering retry fallback', [
                        'trace_id' => $state->traceId,
                        'error'    => $state->reason,
                    ]);

                    // Reset for retry
                    $state->isRetry = true;
                    $state->escalated = false;
                    $state->guardrailFailed = false;

                    // Remove completed steps for reasoning & guardrail so they run again
                    $state->completedSteps = array_diff($state->completedSteps, ['llm_reasoning', 'guardrail_validation']);

                    // Re-run llm_reasoning node
                    $state = $this->reasoningNode->handle($state);
                    $state->completedSteps[] = 'llm_reasoning';

                    // Re-run guardrail_validation node
                    $state = $this->guardrailNode->handle($state);
                    $state->completedSteps[] = 'guardrail_validation';
                }

                // Persist state between nodes for diagnostics & resume capabilities
                $this->saveState($state);

            } catch (\Throwable $e) {
                // Failsafe recovery for any node crash
                return $this->handleFailsafe($state, $name, $e);
            }
        }

        return $state;
    }

    private function loadState(string $traceId): ?AgentState
    {
        $row = DB::table('conversation_states')->where('trace_id', $traceId)->first();
        if (!$row) {
            return null;
        }
        return AgentState::fromJson($row->state_data);
    }

    private function saveState(AgentState $state): void
    {
        DB::table('conversation_states')->updateOrInsert(
            ['trace_id' => $state->traceId],
            [
                'lead_id'    => $state->lead?->id,
                'state_data' => $state->toJson(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Globally logs failure state, creates escalation record, and overrides final reply with safe message.
     */
    private function handleFailsafe(AgentState $state, string $nodeName, \Throwable $e): AgentState
    {
        Log::channel('production')->error("Graph execution crashed at node [{$nodeName}]", [
            'trace_id'    => $state->traceId,
            'exception'   => $e->getMessage(),
            'state_dump'  => $state->toJson(),
        ]);

        $state->escalated = true;
        $state->reason = "Node [{$nodeName}] crashed: " . $e->getMessage();
        $state->finalDecision = [
            'intent'            => 'complaint_legal',
            'decision'          => 'escalate',
            'confidence'        => 1.0,
            'reply_text'        => 'Our AI assistant is temporarily unavailable. A human agent will respond shortly.',
            'document_required' => null,
            'escalate'          => true,
            'ai_score'          => 'warm',
            'reason'            => $state->reason,
            'cited_facts'       => [],
        ];

        // Create human escalation record in AI decisions table so a support rep is assigned
        try {
            AiDecision::create([
                'id'            => (string) Str::uuid(),
                'lead_id'       => $state->lead?->id,
                'trace_id'      => $state->traceId,
                'intent'        => 'complaint_legal',
                'region'        => $state->region,
                'decision_type' => 'escalate',
                'confidence'    => 1.0,
                'prompt'        => $state->promptPayload,
                'response'      => $state->finalDecision,
            ]);
        } catch (\Throwable $escalationEx) {
            Log::channel('production')->critical('Failsafe unable to create DB escalation record', [
                'trace_id' => $state->traceId,
                'error'    => $escalationEx->getMessage(),
            ]);
        }

        // Run ActionExecutionNode to send the fallback text and clean up
        try {
            $state = $this->actionNode->handle($state);
            $state->completedSteps[] = 'action_execution';
        } catch (\Throwable $actionEx) {
            Log::channel('production')->critical('Failsafe ActionExecutionNode crashed', [
                'trace_id' => $state->traceId,
                'error'    => $actionEx->getMessage(),
            ]);
        }

        // Run LoggingObservabilityNode to record the failure trace
        try {
            $state = $this->observabilityNode->handle($state);
            $state->completedSteps[] = 'logging_observability';
            $this->saveState($state);
        } catch (\Throwable $logEx) {
            Log::channel('production')->critical('Failsafe LoggingObservabilityNode crashed', [
                'trace_id' => $state->traceId,
            ]);
        }

        return $state;
    }
}

