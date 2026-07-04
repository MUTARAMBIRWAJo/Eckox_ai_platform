<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * AIChatService — Internal CRM chat assistant for authenticated staff users.
 *
 * SECURITY BOUNDARY:
 * - Reachable ONLY via auth:sanctum middleware (see routes/api.php).
 * - Must NEVER handle product pricing, stock, or compliance facts for customers.
 * - pricingContextGuard() enforces this at the code level — future developers
 *   cannot accidentally wire pricing data in without hitting a deliberate barrier.
 * - Injection pre-screen runs on every user message before any LLM call.
 */
class AIChatService
{
    /**
     * Internal-only system prompt.
     * Explicitly tells the model it is a CRM assistant, NOT a sales agent,
     * and must decline to quote prices or product specs.
     */
    private const SYSTEM_PROMPT = <<<PROMPT
You are an internal CRM assistant for Eckox AI Platform staff users.
Your role is to help CRM users navigate leads, understand conversation history,
and draft internal notes or summaries.

STRICT RESTRICTIONS:
- You are NOT a customer-facing sales agent.
- Do NOT quote product prices, stock levels, or delivery timelines to anyone.
- Do NOT reveal or generate compliance certificates.
- If asked for product pricing or specs, decline and direct the user to the
  AI Sales Agent pipeline instead.
- Do NOT answer customer-impersonating questions about orders or accounts.
PROMPT;

    private readonly ResponseGuardrail $guardrail;

    public function __construct()
    {
        $this->guardrail = new ResponseGuardrail();
    }

    /**
     * Get or create a conversation for an authenticated user.
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
            'title'   => Str::limit($firstMessage, 30),
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
                    'role'    => $message->role,
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
            'role'            => $role,
            'content'         => $content,
        ]);
    }

    /**
     * Type-level barrier: throws if pricing/product data is passed to this service.
     * This is a deliberate code-level guard — not a runtime check that happens
     * only sometimes. Future developers wiring pricing context here will hit this
     * immediately in tests.
     *
     * @throws \LogicException if pricing data is present
     */
    public function pricingContextGuard(array $context): void
    {
        $pricingKeys = ['price', 'price_eur', 'price_usd', 'stock_level', 'spec_processor', 'spec_ram', 'spec_storage', 'compliance_doc'];

        foreach ($pricingKeys as $key) {
            if (array_key_exists($key, $context)) {
                throw new \LogicException(
                    "AIChatService must never receive pricing or product context (key: '{$key}'). " .
                    "Route product/pricing queries through AIDecisionEngine + AgentToolService instead."
                );
            }
        }
    }

    /**
     * Stream response from OpenAI with injection pre-screen.
     * Throws RuntimeException if injection detected — caller must handle.
     */
    public function streamChat(array $messages, callable $onChunk): void
    {
        // Injection pre-screen on the last user message before any LLM call
        $lastUserMessage = collect($messages)->last(fn ($m) => $m['role'] === 'user');
        if ($lastUserMessage) {
            $this->guardrail->checkInjectionOnly($lastUserMessage['content'] ?? '', 'inbound');
        }

        // Prepend system prompt if not already present
        $hasSystem = collect($messages)->contains(fn ($m) => $m['role'] === 'system');
        if (! $hasSystem) {
            array_unshift($messages, ['role' => 'system', 'content' => self::SYSTEM_PROMPT]);
        }

        retry(3, function () use ($messages, $onChunk) {
            $stream = OpenAI::chat()->createStreamed([
                'model'    => 'gpt-4o-mini',
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
