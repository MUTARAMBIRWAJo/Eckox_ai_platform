<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\AgentToolService;
use App\Services\AI\AIContextBuilderService;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Log;

class LlmReasoningNode implements AgentNode
{
    private const MAX_LOOP = 5;

    public function __construct(
        private readonly ToolRouterNode $toolRouter,
        private readonly AIContextBuilderService $contextBuilder
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'llm_reasoning';
            $state->latencyMs['llm_reasoning'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // Build the prompt payload (Task 1 / 3 RAG integration)
        $redactedContent = $this->redactPII($state->message?->content ?? '');
        $state->promptPayload = $this->contextBuilder->buildPrompt(
            region:               $state->region,
            language:             $state->language,
            content:              $redactedContent,
            intent:               $state->intent,
            conversationHistory:  $state->history,
            lead:                 $state->lead,
            retrievalContextData: $state->retrievalContext ?? [],
        );

        if ($state->isRetry) {
            $state->promptPayload['system'] .= "\n\nCRTRY WARNING:\nYou previously generated a response that failed guardrail validation: {$state->reason}.\nYou must resolve this. Ensure cited prices, specs, and compliance references match the tool results you received verbatim.";
        }

        // Build messages thread
        $messages = [
            ['role' => 'system', 'content' => $state->promptPayload['system']],
        ];

        if (!empty($state->promptPayload['history']) && $state->promptPayload['history'] !== 'No prior conversation.') {
            $messages[] = ['role' => 'user', 'content' => "Conversation history:\n" . $state->promptPayload['history']];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "Lead context: {$state->promptPayload['context']}\n\nIncoming message: {$state->promptPayload['message']}",
        ];

        $loopCount = 0;

        while (true) {
            $loopCount++;
            if ($loopCount > self::MAX_LOOP) {
                Log::channel('production')->warning('Multi-LLM tool execution loop cap exceeded', [
                    'trace_id' => $state->traceId,
                ]);
                $state->escalated = true;
                $state->reason = "Tool execution loop cap of " . self::MAX_LOOP . " exceeded.";
                $state->finalDecision = $this->getEscalationResponse($state->reason);
                break;
            }

            // Call Multi-LLM completion
            $responseObj = $this->runMultiLlmCompletion($messages, $state);

            if (empty($responseObj)) {
                // All providers failed
                $state->escalated = true;
                $state->reason = "All LLM providers failed to complete the request.";
                $state->finalDecision = $this->getEscalationResponse($state->reason);
                break;
            }

            // Log the provider that served this turn
            $state->llmProvider = $responseObj['provider'];
            $choice = $responseObj['choice'];

            // Process tool calls
            $toolCallsRaw = $choice['message']['tool_calls'] ?? [];

            // Add assistant reply to message log
            $assistantMsg = ['role' => 'assistant'];
            if (!empty($choice['message']['content'])) {
                $assistantMsg['content'] = $choice['message']['content'];
            }
            if (!empty($toolCallsRaw)) {
                $assistantMsg['tool_calls'] = $toolCallsRaw;
            }
            $messages[] = $assistantMsg;

            if (empty($toolCallsRaw)) {
                // LLM completed reasoning, parse final response
                $content = $choice['message']['content'] ?? '{}';
                $decoded = json_decode($content, true);

                if (!is_array($decoded) || (!isset($decoded['decision']) && !isset($decoded['reply_text']))) {
                    // Fallback JSON parsing
                    $decoded = [
                        'intent'     => $state->intent,
                        'decision'   => 'reply',
                        'confidence' => 0.5,
                        'reply_text' => $content,
                    ];
                }

                if (!empty($decoded['escalate']) || ($decoded['decision'] ?? '') === 'escalate') {
                    $state->escalated = true;
                    $state->reason = $decoded['reason'] ?? 'LLM requested escalation';
                    $decoded = $this->getEscalationResponse($state->reason);
                }

                if (isset($decoded['intent'])) {
                    $state->intent = $decoded['intent'];
                }

                $state->llmRawResponse = $decoded;
                $state->finalDecision = $decoded;
                break;
            }

            // Prepare state for ToolRouterNode
            $state->toolCalls = array_map(fn ($tc) => [
                'id'        => $tc['id'],
                'name'      => $tc['name'] ?? $tc['function']['name'],
                'arguments' => is_array($tc['arguments'] ?? null) ? $tc['arguments'] : (json_decode($tc['function']['arguments'] ?? '{}', true) ?? []),
                'status'    => 'requested',
            ], $toolCallsRaw);

            // Execute tools via ToolRouterNode
            $state = $this->toolRouter->handle($state);

            // Append tool results to messages thread
            foreach ($state->toolCalls as $processedCall) {
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $processedCall['id'],
                    'content'      => json_encode($processedCall['result'] ?? ['error' => 'No result returned']),
                ];
            }
        }

        $state->nodePath[] = 'llm_reasoning';
        $state->latencyMs['llm_reasoning'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    /**
     * Tries providers sequentially: OpenAI -> Anthropic -> Groq
     */
    private function runMultiLlmCompletion(array $messages, AgentState $state): ?array
    {
        $providers = [
            'openai'    => OpenAIProvider::class,
            'anthropic' => AnthropicProvider::class,
            'groq'      => GroqProvider::class,
        ];

        $tools = $this->buildOpenAIToolDefinitions();

        foreach ($providers as $name => $class) {
            try {
                $client = app($class);
                $result = $client->generate($messages, $tools, $state);
                if ($result) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::channel('production')->warning("Provider [{$name}] failed in multi-LLM router", [
                    'trace_id' => $state->traceId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

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
            'send_whatsapp_message' => ['to' => 'string', 'message' => 'string'],
            'send_email'          => ['to' => 'string', 'subject' => 'string', 'body' => 'string'],
            'schedule_followup'   => ['lead_id' => 'integer', 'date' => 'string', 'description' => 'string'],
        ];

        $required = [
            'get_product_price'  => ['sku', 'region'],
            'check_stock'        => ['sku'],
            'get_product_spec'   => ['sku'],
            'get_compliance_doc' => ['region', 'doc_type'],
            'create_quote_pdf'   => ['sku', 'region', 'quantity'],
            'generate_invoice'   => ['sku', 'region', 'quantity'],
            'escalate_to_human'  => ['reason'],
            'send_whatsapp_message' => ['to', 'message'],
            'send_email'          => ['to', 'subject', 'body'],
            'schedule_followup'   => ['lead_id', 'date', 'description'],
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

    private function redactPII(string $text): string
    {
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        $text = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $text);
        return $text;
    }
}
