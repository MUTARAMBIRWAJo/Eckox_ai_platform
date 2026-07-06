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

            try {
                $provider = $this->llmRouter->getProvider($providerName);
                $state = new \App\Services\AI\AgentState('stream-' . uniqid());

                $generator = $provider->stream($messages, $state);

                $fullReply = '';
                foreach ($generator as $chunk) {
                    $fullReply .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
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
                ob_flush();
                flush();
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
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
