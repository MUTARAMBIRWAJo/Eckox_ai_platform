<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AIChatService
{
    /**
     * Get or create a conversation.
     */
    public function getOrCreateConversation(User $user, ?string $conversationId, string $firstMessage): Conversation
    {
        if ($conversationId) {
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();
            if ($conversation) {
                return $conversation;
            }
        }

        return Conversation::create([
            'user_id' => $user->id,
            'title' => Str::limit($firstMessage, 30),
        ]);
    }

    /**
     * Get message history for a conversation.
     */
    public function getHistory(Conversation $conversation): array
    {
        return Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Save a message to the database.
     */
    public function saveMessage(Conversation $conversation, string $role, string $content): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
        ]);
    }

    /**
     * Stream response from OpenAI.
     */
    public function streamChat(array $messages, callable $onChunk): void
    {
        retry(3, function () use ($messages, $onChunk) {
            $stream = OpenAI::chat()->createStreamed([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
            ]);

            foreach ($stream as $response) {
                $text = $response->choices[0]->delta->content ?? '';
                if ($text !== '') {
                    $onChunk($text);
                }
            }
        }, 500);
    }
}
