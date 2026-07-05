<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\AgentToolService;
use App\Services\AI\AIContextBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

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
            $state->promptPayload['system'] .= "\n\nCRITICAL RETRY WARNING:\nYou previously generated a response that failed guardrail validation: {$state->reason}.\nYou must resolve this. Ensure cited prices, specs, and compliance references match the tool results you received verbatim.";
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
                'name'      => $tc['function']['name'],
                'arguments' => json_decode($tc['function']['arguments'], true) ?? [],
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
        // 1. Try OpenAI
        try {
            $tools = $this->buildOpenAIToolDefinitions();
            $response = OpenAI::chat()->create([
                'model'       => config('openai.model', 'gpt-4o-mini'),
                'messages'    => $messages,
                'temperature' => 0.1,
                'max_tokens'  => 800,
                'tools'       => $tools,
                'tool_choice' => 'auto',
            ]);

            $choice = $response->choices[0];
            $toolCalls = [];
            if (!empty($choice->message->toolCalls)) {
                $toolCalls = array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->function->name,
                        'arguments' => $tc->function->arguments,
                    ]
                ], $choice->message->toolCalls);
            }

            return [
                'provider' => 'openai',
                'choice'   => [
                    'finish_reason' => $choice->finishReason ?? 'stop',
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $choice->message->content,
                        'tool_calls' => $toolCalls,
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            Log::channel('production')->warning('Primary OpenAI provider failed, switching to Anthropic Claude', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);
        }

        // 2. Try Anthropic Claude
        try {
            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            $system = collect($messages)->first(fn ($m) => $m['role'] === 'system')['content'] ?? '';
            $chatMessages = collect($messages)->filter(fn ($m) => $m['role'] !== 'system')->values()->toArray();

            // Convert OpenAI tools to Anthropic format
            $anthropicTools = $this->buildAnthropicTools();

            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 1024,
                'system'     => $system,
                'messages'   => $this->mapMessagesToAnthropic($chatMessages),
                'tools'      => $anthropicTools,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $contentBlocks = $data['content'] ?? [];
                $text = '';
                $toolCalls = [];

                foreach ($contentBlocks as $block) {
                    if ($block['type'] === 'text') {
                        $text .= $block['text'];
                    } elseif ($block['type'] === 'tool_use') {
                        $toolCalls[] = [
                            'id' => $block['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $block['name'],
                                'arguments' => json_encode($block['input']),
                            ]
                        ];
                    }
                }

                return [
                    'provider' => 'anthropic',
                    'choice'   => [
                        'finish_reason' => empty($toolCalls) ? 'stop' : 'tool_calls',
                        'message' => [
                            'role'       => 'assistant',
                            'content'    => $text,
                            'tool_calls' => $toolCalls,
                        ]
                    ]
                ];
            } else {
                throw new \RuntimeException('Anthropic API error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::channel('production')->warning('Secondary Anthropic provider failed, switching to Groq', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);
        }

        // 3. Try Groq LLaMA 3.1
        try {
            $apiKey = config('services.groq.key') ?: env('GROQ_API_KEY');
            $tools = $this->buildOpenAIToolDefinitions();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama3-70b-8192',
                'messages'    => $messages,
                'temperature' => 0.1,
                'max_tokens'  => 800,
                'tools'       => $tools,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0];
                return [
                    'provider' => 'groq',
                    'choice'   => [
                        'finish_reason' => $choice['finish_reason'] ?? 'stop',
                        'message' => [
                            'role'       => 'assistant',
                            'content'    => $choice['message']['content'] ?? null,
                            'tool_calls' => $choice['message']['tool_calls'] ?? [],
                        ]
                    ]
                ];
            } else {
                throw new \RuntimeException('Groq API error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::channel('production')->error('All LLM providers failed in multi-LLM router', [
                'trace_id' => $state->traceId,
                'error'    => $e->getMessage(),
            ]);
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

    private function buildAnthropicTools(): array
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
                'name'        => $name,
                'description' => $tool['description'],
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => $properties,
                    'required'   => $required[$name] ?? [],
                ]
            ];
        }, AgentToolService::TOOL_DEFINITIONS);
    }

    private function mapMessagesToAnthropic(array $messages): array
    {
        return array_map(function ($m) {
            $role = $m['role'];
            if ($role === 'system') {
                $role = 'user';
            }
            if ($role === 'tool') {
                return [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Tool response for call {$m['tool_call_id']}: {$m['content']}",
                        ]
                    ]
                ];
            }
            return [
                'role'    => $role,
                'content' => $m['content'] ?? '',
            ];
        }, $messages);
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
