<?php

namespace App\Services\AI;

use App\Events\AIDecisionGenerated;
use App\Events\LeadScored;
use App\Models\AiDecision;
use App\Models\InboundMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AIDecisionEngine
{
    /** Maximum tool calls allowed per turn before forced escalation. */
    private const MAX_TOOL_CALLS = 5;

    public function __construct(
        private readonly AIContextBuilderService $contextBuilder,
        private readonly EscalationGuard         $escalationGuard,
        private readonly ResponseGuardrail       $responseGuardrail,
        private readonly AgentToolService        $agentTools,
    ) {}

    /**
     * Primary entry point: analyse an inbound message and emit an AI decision.
     */
    public function analyse(InboundMessage $message, ?Lead $lead = null): AiDecision
    {
        $startedAt = microtime(true);
        $traceId   = (string) Str::uuid();

        $region          = $this->contextBuilder->detectRegion($message->country ?? '');
        $redactedContent = $this->redactPII($message->content);
        $language        = $message->language ?? $this->contextBuilder->detectLanguage($redactedContent);
        $intent          = $this->contextBuilder->detectIntent($redactedContent);

        // Layer 2 — KB passages ONLY (no product pre-fetch — structured facts come via tool calls)
        $retrievalContext = RetrievalContext::buildKbOnly($redactedContent, $region, $language);

        // Build conversation history (PII-scrubbed)
        $history = $lead
            ? $lead->inboundMessages()
                   ->latest()
                   ->take(10)
                   ->get()
                   ->map(fn ($m) => ['role' => 'user', 'content' => $this->redactPII($m->content)])
                   ->toArray()
            : [];

        $promptPayload = $this->contextBuilder->buildPrompt(
            region:               $region,
            language:             $language,
            content:              $redactedContent,
            intent:               $intent,
            conversationHistory:  $history,
            lead:                 $lead,
            retrievalContextData: $retrievalContext->toLLMContext(),
        );

        // 1a. Injection pre-screen BEFORE any LLM call (Layer 4 — Security)
        try {
            $this->responseGuardrail->checkInjectionOnly($message->content, 'inbound');
        } catch (\Throwable $injEx) {
            Log::channel('production')->warning('Prompt injection detected — immediate escalation', [
                'trace_id'   => $traceId,
                'message_id' => $message->id,
                'reason'     => $injEx->getMessage(),
            ]);

            return $this->forceEscalation($lead, $traceId, $region, $promptPayload, $startedAt, [
                'escalate'   => true,
                'reason'     => 'Injection detected: ' . $injEx->getMessage(),
                'confidence' => 1.0,
                'intent'     => 'complaint_legal',
            ]);
        }

        // 1b. EscalationGuard (regex + LLM classification)
        $guardResult = $this->escalationGuard->check($message, $lead, $region);
        if ($guardResult['escalate']) {
            return $this->forceEscalation($lead, $traceId, $region, $promptPayload, $startedAt, $guardResult);
        }

        // 2. Tool-calling LLM flow with guardrail + fallback
        $rawDecision = $this->executeLlmFlowWithFallback(
            $promptPayload, $message, $retrievalContext, $traceId, $region, $lead
        );

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('production')->info('AI Decision generated', [
            'trace_id'   => $traceId,
            'region'     => $region,
            'channel'    => $message->channel,
            'intent'     => $intent,
            'decision'   => $rawDecision['decision'] ?? 'unknown',
            'confidence' => $rawDecision['confidence'] ?? 0,
            'latency_ms' => $latencyMs,
        ]);

        $decision = AiDecision::create([
            'id'            => (string) Str::uuid(),
            'lead_id'       => $lead?->id,
            'trace_id'      => $traceId,
            'intent'        => $rawDecision['intent'] ?? $intent,
            'region'        => $region,
            'decision_type' => $rawDecision['decision'] ?? 'reply',
            'confidence'    => $rawDecision['confidence'] ?? 0.5,
            'prompt'        => $promptPayload,
            'response'      => $rawDecision,
        ]);

        if ($lead && isset($rawDecision['ai_score'])) {
            $lead->update([
                'ai_score'        => $rawDecision['ai_score'],
                'region'          => $region,
                'language'        => $language,
                'last_message_at' => now(),
            ]);
            event(new LeadScored($lead, $rawDecision['ai_score'], $region, $traceId));
        }

        event(new AIDecisionGenerated($decision));

        return $decision;
    }

    // =========================================================================
    // Tool-calling LLM flow
    // =========================================================================

    /**
     * Execute LLM flow with tool-calling loop, guardrail validation, and fallback.
     */
    private function executeLlmFlowWithFallback(
        array $promptPayload,
        InboundMessage $message,
        RetrievalContext $retrievalContext,
        string $traceId,
        string $region,
        ?Lead $lead
    ): array {
        $rawDecision = $this->callOpenAIWithTools($promptPayload, $message, $traceId, $region, $lead);

        try {
            $this->responseGuardrail->check($message->content, $rawDecision, $retrievalContext);
            return $rawDecision;
        } catch (\Throwable $e) {
            Log::channel('production')->warning('First guardrail check failed, triggering retry fallback', [
                'trace_id'   => $traceId,
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            $retryPayload            = $promptPayload;
            $retryPayload['system'] .= "\n\nCRITICAL RETRY WARNING:\nYou previously generated a response that failed guardrail validation: {$e->getMessage()}.\nEnsure cited prices, specs, and compliance references match the tool results you received verbatim.";

            $retryDecision = $this->callOpenAIWithTools($retryPayload, $message, $traceId, $region, $lead);

            try {
                $this->responseGuardrail->check($message->content, $retryDecision, $retrievalContext);
                return $retryDecision;
            } catch (\Throwable $e2) {
                Log::channel('production')->error('Second guardrail retry failed, forcing human escalation', [
                    'trace_id'   => $traceId,
                    'message_id' => $message->id,
                    'error'      => $e2->getMessage(),
                ]);

                Log::channel('production')->warning('Guardrail fallback event recorded', [
                    'trace_id' => $traceId,
                    'cause'    => $e2->getMessage(),
                    'region'   => $region,
                ]);

                return $this->getEscalationResponse('Guardrail violation fallback: ' . $e2->getMessage());
            }
        }
    }

    /**
     * Call OpenAI with tool definitions and run the tool-call dispatch loop.
     * Structured facts (price, spec, stock) enter ONLY via tool results here.
     * Max MAX_TOOL_CALLS tool calls per turn; exceeding cap forces escalation.
     */
    private function callOpenAIWithTools(
        array $promptPayload,
        InboundMessage $message,
        string $traceId,
        string $region,
        ?Lead $lead
    ): array {
        try {
            // Build OpenAI tool definitions from AgentToolService registry
            $tools = $this->buildOpenAIToolDefinitions();

            $messages = [
                ['role' => 'system', 'content' => $promptPayload['system']],
            ];

            if (!empty($promptPayload['history']) && $promptPayload['history'] !== 'No prior conversation.') {
                $messages[] = ['role' => 'user', 'content' => "Conversation history:\n" . $promptPayload['history']];
            }

            $messages[] = [
                'role'    => 'user',
                'content' => "Lead context: {$promptPayload['context']}\n\nIncoming message: {$promptPayload['message']}",
            ];

            $toolCallCount = 0;

            // Tool-calling loop
            while (true) {
                $response = OpenAI::chat()->create([
                    'model'       => config('openai.model', 'gpt-4o-mini'),
                    'messages'    => $messages,
                    'temperature' => 0.1,
                    'max_tokens'  => 800,
                    'tools'       => $tools,
                    'tool_choice' => 'auto',
                ]);

                $choice      = $response->choices[0];
                $finishReason = $choice->finishReason ?? 'stop';

                // Append assistant message (may contain tool_calls)
                $assistantMsg = ['role' => 'assistant'];
                if (!empty($choice->message->content)) {
                    $assistantMsg['content'] = $choice->message->content;
                }
                $toolCallsRaw = $choice->message->toolCalls ?? [];
                if (!empty($toolCallsRaw)) {
                    $assistantMsg['tool_calls'] = array_map(fn ($tc) => [
                        'id'       => $tc->id,
                        'type'     => 'function',
                        'function' => [
                            'name'      => $tc->function->name,
                            'arguments' => $tc->function->arguments,
                        ],
                    ], $toolCallsRaw);
                }
                $messages[] = $assistantMsg;

                // If LLM is done (no more tool calls), parse final response
                if ($finishReason === 'stop' || empty($toolCallsRaw)) {
                    $content = $choice->message->content ?? '{}';
                    $decoded = json_decode($content, true);

                    if (!is_array($decoded) || (!isset($decoded['decision']) && !isset($decoded['reply_text']))) {
                        throw new \RuntimeException('Structured decision key missing in LLM response');
                    }

                    if (!isset($decoded['decision'])) {
                        $decoded['decision'] = 'reply';
                    }

                    if (!empty($decoded['escalate'])) {
                        return $this->getEscalationResponse('LLM requested escalation via response flag.');
                    }

                    return $decoded;
                }

                // Process tool calls
                if ($finishReason === 'tool_calls') {
                    foreach ($toolCallsRaw as $toolCall) {
                        $toolCallCount++;

                        // Cap check — escalate rather than answer with insufficient data
                        if ($toolCallCount > self::MAX_TOOL_CALLS) {
                            Log::channel('production')->warning('Tool-call loop cap exceeded', [
                                'trace_id'       => $traceId,
                                'message_id'     => $message->id,
                                'tool_call_count' => $toolCallCount,
                            ]);
                            return $this->getEscalationResponse('Tool-call loop exceeded maximum depth. Escalating to human.');
                        }

                        $toolName   = $toolCall->function->name;
                        $toolInputs = json_decode($toolCall->function->arguments, true) ?? [];

                        // Inject lead context into document tools
                        if (in_array($toolName, ['create_quote_pdf', 'generate_invoice']) && $lead) {
                            $toolInputs['lead_id'] = $toolInputs['lead_id'] ?? $lead->id;
                            $toolInputs['region']  = $toolInputs['region']  ?? $region;
                        }
                        if ($toolName === 'escalate_to_human') {
                            $toolInputs['trace_id'] = $traceId;
                            $toolInputs['lead_id']  = $toolInputs['lead_id'] ?? $lead?->id;
                        }

                        // Dispatch through AgentToolService (all fact sourcing happens here)
                        try {
                            $toolResult = $this->agentTools->dispatch($toolName, $toolInputs, $traceId);
                        } catch (\InvalidArgumentException $toolEx) {
                            $toolResult = ['error' => $toolEx->getMessage()];
                            Log::channel('production')->error('Tool dispatch error', [
                                'trace_id' => $traceId,
                                'tool'     => $toolName,
                                'error'    => $toolEx->getMessage(),
                            ]);
                        }

                        // Return tool result to LLM as tool message
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content'      => json_encode($toolResult),
                        ];

                        // If the tool itself requested escalation, honour it immediately
                        if ($toolName === 'escalate_to_human' && ($toolResult['escalated'] ?? false)) {
                            return $this->getEscalationResponse($toolResult['reason'] ?? 'Tool-initiated escalation.');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('production')->error('AI tool-calling LLM flow crashed. Defaulting to escalation.', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            return $this->getEscalationResponse('Fail-safe recovery fallback activated: ' . $e->getMessage());
        }
    }

    /**
     * Build OpenAI-compatible function definitions from AgentToolService::TOOL_DEFINITIONS.
     * Converts our simple registry format to the OpenAI tools schema.
     */
    private function buildOpenAIToolDefinitions(): array
    {
        $parameterSchemas = [
            'get_product_price'  => ['sku' => 'string', 'region' => 'string'],
            'check_stock'        => ['sku' => 'string'],
            'get_product_spec'   => ['sku' => 'string'],
            'get_compliance_doc' => ['region' => 'string', 'doc_type' => 'string'],
            'create_quote_pdf'   => ['lead_id' => 'integer', 'sku' => 'string', 'region' => 'string', 'quantity' => 'integer'],
            'generate_invoice'   => ['lead_id' => 'integer', 'sku' => 'string', 'region' => 'string', 'quantity' => 'integer'],
            'escalate_to_human'  => ['reason' => 'string'],
        ];

        $required = [
            'get_product_price'  => ['sku', 'region'],
            'check_stock'        => ['sku'],
            'get_product_spec'   => ['sku'],
            'get_compliance_doc' => ['region', 'doc_type'],
            'create_quote_pdf'   => ['sku', 'region', 'quantity'],
            'generate_invoice'   => ['sku', 'region', 'quantity'],
            'escalate_to_human'  => ['reason'],
        ];

        return array_map(function ($tool) use ($parameterSchemas, $required) {
            $name       = $tool['name'];
            $properties = [];

            foreach (($parameterSchemas[$name] ?? []) as $param => $type) {
                $properties[$param] = ['type' => $type];
            }

            return [
                'type'     => 'function',
                'function' => [
                    'name'        => $name,
                    'description' => $tool['description'],
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => $properties,
                        'required'   => $required[$name] ?? [],
                    ],
                ],
            ];
        }, AgentToolService::TOOL_DEFINITIONS);
    }

    /**
     * Map structured default escalation response array.
     */
    private function getEscalationResponse(string $reason): array
    {
        return [
            'intent'            => 'complaint_legal',
            'decision'          => 'escalate',
            'confidence'        => 1.0,
            'reply_text'        => 'Let me confirm this with our team and follow up shortly.',
            'document_required' => null,
            'escalate'          => true,
            'ai_score'          => 'warm',
            'reason'            => $reason,
            'cited_facts'       => [],
        ];
    }

    /**
     * Force an escalation decision.
     */
    private function forceEscalation(
        ?Lead $lead,
        string $traceId,
        string $region,
        array $promptPayload,
        float $startedAt,
        array $guardResult
    ): AiDecision {
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('production')->warning('AI forced escalation hook activated', [
            'trace_id'   => $traceId,
            'region'     => $region,
            'latency_ms' => $latencyMs,
            'reason'     => $guardResult['reason'] ?? 'Unknown',
        ]);

        $responsePayload = $this->getEscalationResponse($guardResult['reason'] ?? 'Security escalation guard rules triggered.');

        $decision = AiDecision::create([
            'id'            => (string) Str::uuid(),
            'lead_id'       => $lead?->id,
            'trace_id'      => $traceId,
            'intent'        => $guardResult['intent'] ?? 'complaint_legal',
            'region'        => $region,
            'decision_type' => 'escalate',
            'confidence'    => 1.0,
            'prompt'        => $promptPayload,
            'response'      => $responsePayload,
        ]);

        event(new AIDecisionGenerated($decision));

        return $decision;
    }

    /**
     * Scrub PII from input texts (emails, phones).
     */
    private function redactPII(string $text): string
    {
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        // Robust international phone regex supporting area codes, country codes, spaces/dashes/parentheses
        $text = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $text);

        return $text;
    }
}
