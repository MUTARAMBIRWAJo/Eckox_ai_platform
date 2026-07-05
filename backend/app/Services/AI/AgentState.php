<?php

namespace App\Services\AI;

use App\Models\InboundMessage;
use App\Models\Lead;

class AgentState
{
    public string $traceId;
    public ?InboundMessage $message = null;
    public ?Lead $lead = null;
    public string $region = 'africa';
    public string $language = 'en';
    public string $intent = 'general';
    public ?array $retrievalContext = null;
    public array $history = [];
    public array $promptPayload = [];
    public ?array $llmRawResponse = null;
    public ?string $llmProvider = null;
    public array $toolCalls = [];
    public ?array $guardrailVerdict = null;
    public array $finalDecision = [];
    public array $actionsTaken = [];
    public array $nodePath = [];
    public array $latencyMs = [];
    public bool $escalated = false;
    public bool $guardrailFailed = false; // distinct flag for factual/compliance guardrail failures
    public string $reason = '';
    public bool $isRetry = false;
    public array $completedSteps = []; // for idempotency/retry tracking

    public function __construct(string $traceId)
    {
        $this->traceId = $traceId;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        $state = new self($data['trace_id'] ?? '');
        $state->region = $data['region'] ?? 'africa';
        $state->language = $data['language'] ?? 'en';
        $state->intent = $data['intent'] ?? 'general';
        $state->retrievalContext = $data['retrieval_context'] ?? null;
        $state->history = $data['history'] ?? [];
        $state->promptPayload = $data['prompt_payload'] ?? [];
        $state->llmRawResponse = $data['llm_raw_response'] ?? null;
        $state->llmProvider = $data['llm_provider'] ?? null;
        $state->toolCalls = $data['tool_calls'] ?? [];
        $state->guardrailVerdict = $data['guardrail_verdict'] ?? null;
        $state->finalDecision = $data['final_decision'] ?? [];
        $state->actionsTaken = $data['actions_taken'] ?? [];
        $state->nodePath = $data['node_path'] ?? [];
        $state->latencyMs = $data['latency_ms'] ?? [];
        $state->escalated = $data['escalated'] ?? false;
        $state->guardrailFailed = $data['guardrail_failed'] ?? false;
        $state->reason = $data['reason'] ?? '';
        $state->isRetry = $data['is_retry'] ?? false;
        $state->completedSteps = $data['completed_steps'] ?? [];

        if (!empty($data['message_id'])) {
            $state->message = InboundMessage::find($data['message_id']);
        }
        if (!empty($data['lead_id'])) {
            $state->lead = Lead::find($data['lead_id']);
        }

        return $state;
    }

    public function toJson(): string
    {
        return json_encode([
            'trace_id' => $this->traceId,
            'message_id' => $this->message?->id,
            'lead_id' => $this->lead?->id,
            'region' => $this->region,
            'language' => $this->language,
            'intent' => $this->intent,
            'retrieval_context' => $this->retrievalContext,
            'history' => $this->history,
            'prompt_payload' => $this->promptPayload,
            'llm_raw_response' => $this->llmRawResponse,
            'llm_provider' => $this->llmProvider,
            'tool_calls' => $this->toolCalls,
            'guardrail_verdict' => $this->guardrailVerdict,
            'final_decision' => $this->finalDecision,
            'actions_taken' => $this->actionsTaken,
            'node_path' => $this->nodePath,
            'latency_ms' => $this->latencyMs,
            'escalated' => $this->escalated,
            'guardrail_failed' => $this->guardrailFailed,
            'reason' => $this->reason,
            'is_retry' => $this->isRetry,
            'completed_steps' => $this->completedSteps,
        ]);
    }
}
