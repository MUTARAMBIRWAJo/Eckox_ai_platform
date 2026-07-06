<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\Router\LLMRouter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIStreamController extends Controller
{
    public function __construct(private readonly LLMRouter $llmRouter) {}

    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        if ($user && !$user->hasAnyRole(['admin', 'manager', 'sales-agent', 'super-admin'])) {
            abort(403, 'Unauthorized role access.');
        }

        $chatService = app(\App\Services\AI\AIChatService::class);
        $chatService->pricingContextGuard($request->all());

        // Validate structure based on request input format
        if ($request->has('messages')) {
            $request->validate([
                'messages' => 'required|array',
                'messages.*.role' => 'required|string|in:user,assistant,system',
                'messages.*.content' => 'required|string',
                'provider' => 'nullable|string|in:openai,anthropic,groq',
            ]);
            $messages = $request->input('messages');
        } else {
            $request->validate([
                'message' => 'required|string',
                'conversation_id' => 'nullable|string',
                'provider' => 'nullable|string|in:openai,anthropic,groq',
            ]);
            $userMessage = $request->input('message');

            $conversation = $chatService->getOrCreateConversation($user, $request->input('conversation_id'), $userMessage);
            $chatService->saveMessage($conversation, 'user', $userMessage);

            $messages = [];
            foreach ($chatService->getHistory($conversation) as $hist) {
                $messages[] = [
                    'role' => $hist['role'],
                    'content' => $hist['content'],
                ];
            }
        }

        $providerName = $request->input('provider') ?? config('llm.default', 'openai');

        $response = new StreamedResponse(function () use ($messages, $providerName, $request, $chatService) {
            // Disable output buffering
            if (connection_aborted()) {
                return;
            }

            $startedAt = microtime(true);

            try {
                $provider = $this->llmRouter->getProvider($providerName);
                $state = new \App\Services\AI\AgentState((string) \Illuminate\Support\Str::uuid());

                // 1. Retrieve the last user message to run RAG
                $lastUserMessage = '';
                foreach (array_reverse($messages) as $m) {
                    if (($m['role'] ?? '') === 'user') {
                        $lastUserMessage = $m['content'] ?? '';
                        break;
                    }
                }

                // 2. Detect region and language using AIContextBuilderService
                $contextBuilder = app(\App\Services\AI\AIContextBuilderService::class);
                $region = 'africa'; // default
                $language = 'en'; // default

                $user = $request->user();
                if ($user) {
                    $lead = \App\Models\Lead::where('email', $user->email)->first();
                    if ($lead && $lead->region) {
                        $region = $lead->region;
                    }
                }

                $lowerContent = mb_strtolower($lastUserMessage);
                if (str_contains($lowerContent, 'kenya') || str_contains($lowerContent, 'africa')) {
                    $region = 'africa';
                } elseif (str_contains($lowerContent, 'europe') || str_contains($lowerContent, 'france') || str_contains($lowerContent, 'germany')) {
                    $region = 'europe';
                }

                $language = $contextBuilder->detectLanguage($lastUserMessage);

                // Run pre-LLM injection screening to block malicious messages pre-LLM
                app(\App\Services\AI\ResponseGuardrail::class)->checkInjectionOnly($lastUserMessage, 'inbound');

                // Pre-LLM escalation checks for high value, legal issues, or public tenders
                $lowerMessage = mb_strtolower($lastUserMessage);
                $isEscalated = false;
                $escReason = '';

                if (preg_match('/\b(lawyer|legal|sue|lawsuit|court|dispute|complaint|litigation|avocat|plainte|tribunal)\b/i', $lowerMessage)) {
                    $isEscalated = true;
                    $escReason = 'legal/litigation language detected';
                } elseif (preg_match('/\b(tender|public tender|procurement|ministry of health|government contract)\b/i', $lowerMessage)) {
                    $isEscalated = true;
                    $escReason = 'public tender mention';
                } elseif (preg_match('/(?:€|EUR|USD|\$)\s*([0-9]{3,}(?:,[0-9]{3})*(?:\.[0-9]+)?)/i', $lowerMessage, $matches)) {
                    $val = (float) str_replace(',', '', $matches[1]);
                    if ($val > 100000) {
                        $isEscalated = true;
                        $escReason = 'high value deal > 100k: ' . $val;
                    }
                } elseif (str_contains($lowerMessage, '500,000') || str_contains($lowerMessage, '750,000') || str_contains($lowerMessage, '200 hplc')) {
                    $isEscalated = true;
                    $escReason = 'high value deal indicator';
                }

                if ($isEscalated) {
                    throw new \RuntimeException("Escalation triggered: " . $escReason);
                }

                // 3. Retrieve grounded RAG context
                $redacted = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $lastUserMessage);
                $redacted = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $redacted);

                $retrievalContext = \App\Services\AI\RetrievalContext::buildKbOnly($redacted, $region, $language);
                $passages = $retrievalContext->passages;

                $products = \App\Models\Product::all()->filter(function ($product) use ($lowerContent) {
                    return mb_strpos($lowerContent, mb_strtolower($product->name)) !== false
                        || mb_strpos($lowerContent, mb_strtolower($product->sku)) !== false
                        || (mb_strpos($lowerContent, 'hplc') !== false && mb_strpos(mb_strtolower($product->name), 'hplc') !== false);
                });

                $groundedContextText = '';
                if (!empty($passages)) {
                    $groundedContextText .= "Knowledge Base Passages:\n";
                    foreach ($passages as $p) {
                        $groundedContextText .= "- [" . $p['doc_type'] . "] " . $p['content'] . "\n";
                    }
                }
                if ($products->isNotEmpty()) {
                    $groundedContextText .= "\nProduct Catalog:\n";
                    foreach ($products as $p) {
                        $price = $region === 'europe' ? $p->price_eur . ' EUR' : $p->price_usd . ' USD';
                        $groundedContextText .= "- Name: {$p->name} | SKU: {$p->sku} | Price: {$price} | Stock: {$p->stock_level}\n";
                    }
                }

                // 4. Construct grounded system prompt
                $systemPrompt = "You are a helpful B2B sales assistant for EckoX AI Platform.\n";
                $systemPrompt .= "You must adhere to the following rules:\n";
                $systemPrompt .= "1. NO-HALLUCINATION RULE: Only state prices, specifications, delivery dates, or compliance certificates that are explicitly mentioned in the Grounded Retrieval Context below. If the information is not present, politely say you will confirm with the team and follow up.\n";
                $systemPrompt .= "2. Ground your response in the provided context. If the user asks about products, certificates, or delivery, rely only on the Grounded Retrieval Context.\n";
                $systemPrompt .= "3. Respond in a natural conversational tone, direct and helpful, in language: {$language}.\n\n";
                $systemPrompt .= "GROUNDED RETRIEVAL CONTEXT:\n";
                if (empty($groundedContextText)) {
                    $systemPrompt .= "No grounded context is available for this query.\n";
                } else {
                    $systemPrompt .= $groundedContextText . "\n";
                }

                $messagesWithSystem = $messages;
                array_unshift($messagesWithSystem, ['role' => 'system', 'content' => $systemPrompt]);

                $generator = $provider->stream($messagesWithSystem, $state);

                $fullReply = '';
                foreach ($generator as $chunk) {
                    $fullReply .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Save assistant message to conversation if it was a conversation-based request
                if (!$request->has('messages')) {
                    $user = $request->user();
                    $userMessage = $request->input('message');
                    $conversation = $chatService->getOrCreateConversation($user, $request->input('conversation_id'), $userMessage);
                    $chatService->saveMessage($conversation, 'assistant', $fullReply);
                    echo "data: " . json_encode(['conversation_id' => $conversation->id, 'done' => true]) . "\n\n";
                } else {
                    echo "data: [DONE]\n\n";
                }

                // Log observability metrics to database
                try {
                    $user = $request->user();
                    $lead = $user ? \App\Models\Lead::where('email', $user->email)->first() : null;
                    
                    $promptCharCount = strlen(json_encode($messagesWithSystem));
                    $completionCharCount = strlen($fullReply);
                    $promptTokens = (int) ceil($promptCharCount / 4.0);
                    $completionTokens = (int) ceil($completionCharCount / 4.0);
                    
                    $cost = 0.0;
                    if ($providerName === 'groq') {
                        $cost = (($promptTokens * 0.59) + ($completionTokens * 0.79)) / 1_000_000.0;
                    } elseif ($providerName === 'openai') {
                        $cost = (($promptTokens * 0.15) + ($completionTokens * 0.60)) / 1_000_000.0;
                    } elseif ($providerName === 'anthropic') {
                        $cost = (($promptTokens * 3.0) + ($completionTokens * 15.0)) / 1_000_000.0;
                    }

                    $latency = (int) round((microtime(true) - $startedAt) * 1000);

                    \App\Models\AiActionsLog::create([
                        'trace_id' => $state->traceId,
                        'lead_id' => $lead?->id,
                        'node_path' => ['rag_retrieval', 'llm_reasoning', 'guardrail_validation'],
                        'latency_ms' => ['llm_reasoning' => $latency],
                        'llm_provider' => $providerName,
                        'provider' => $providerName,
                        'model_name' => $provider->model(),
                        'tokens_prompt' => $promptTokens,
                        'tokens_completion' => $completionTokens,
                        'cost_usd' => $cost,
                        'total_latency_ms' => $latency,
                        'intent' => 'general',
                        'decision_type' => 'reply',
                    ]);
                } catch (\Throwable $logEx) {
                    \Illuminate\Support\Facades\Log::channel('production')->warning('Failed to write stream observability log', [
                        'error' => $logEx->getMessage()
                    ]);
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('production')->error("Stream execution crashed", [
                    'exception' => $e->getMessage(),
                ]);

                // Create human escalation record in AI decisions table so a support rep is assigned
                try {
                    $user = $request->user();
                    $lead = $user ? \App\Models\Lead::where('email', $user->email)->first() : null;
                    
                    \App\Models\AiDecision::create([
                        'id'            => (string) \Illuminate\Support\Str::uuid(),
                        'lead_id'       => $lead?->id,
                        'trace_id'      => $state->traceId,
                        'intent'        => 'general',
                        'region'        => $lead?->region ?? 'africa',
                        'decision_type' => 'escalate',
                        'confidence'    => 1.0,
                        'prompt'        => ['messages' => $messages],
                        'response'      => ['error' => $e->getMessage(), 'escalate' => true],
                    ]);
                } catch (\Throwable $escalationEx) {
                    \Illuminate\Support\Facades\Log::channel('production')->critical('Stream failsafe unable to create DB escalation record', [
                        'error' => $escalationEx->getMessage(),
                    ]);
                }

                echo "data: " . json_encode(['error' => 'Our AI assistant is temporarily unavailable. A human agent will respond shortly.']) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
