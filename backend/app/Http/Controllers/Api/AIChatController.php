<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIChatController extends Controller
{
    protected AIChatService $aiChatService;

    public function __construct(AIChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    /**
     * Stream OpenAI chat completion response and save to database.
     */
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();
        $messageContent = $request->input('message');
        $conversationId = $request->input('conversation_id');

        \Illuminate\Support\Facades\Log::info('AI streaming request started', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'message_length' => strlen($messageContent),
        ]);

        // 1. Get or create conversation
        $conversation = $this->aiChatService->getOrCreateConversation($user, $conversationId, $messageContent);

        // 2. Save user message to database
        $this->aiChatService->saveMessage($conversation, 'user', $messageContent);

        // 3. Load conversation history
        $history = $this->aiChatService->getHistory($conversation);

        $traceId = class_exists(\Illuminate\Support\Facades\Context::class)
            ? \Illuminate\Support\Facades\Context::get('trace_id')
            : null;

        $response = new StreamedResponse(function () use ($conversation, $history, $user, $traceId) {
            $accumulatedContent = '';
            $startTime = microtime(true);

            // Release session lock to allow concurrent requests during streaming
            if (session_id()) {
                session_write_close();
            }
            // Set time limits safely
            set_time_limit(180);

            if (app()->environment() !== 'testing' && ob_get_level() > 0) {
                ob_end_clean();
            }

            try {
                $this->aiChatService->streamChat($history, function ($chunk) use (&$accumulatedContent) {
                    if (connection_aborted()) {
                        throw new \Exception('Client disconnected');
                    }
                    $accumulatedContent .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    flush();
                });

                if (!empty($accumulatedContent)) {
                    $this->aiChatService->saveMessage($conversation, 'assistant', $accumulatedContent);
                }

                $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                \Illuminate\Support\Facades\Log::info('AI streaming response completed successfully', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'generated_length' => strlen($accumulatedContent),
                    'duration_ms' => $durationMs,
                ]);

                echo "data: " . json_encode(['conversation_id' => $conversation->id, 'done' => true]) . "\n\n";
                flush();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('AI streaming error encountered', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'exception' => $e->getMessage(),
                ]);

                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
