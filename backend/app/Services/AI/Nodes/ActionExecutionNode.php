<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\AgentToolService;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Log;

class ActionExecutionNode implements AgentNode
{
    public function __construct(
        private readonly AgentToolService $agentTools
    ) {}

    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        // Guardrail failure / escalation check
        // If escalated, we execute ONLY the escalation message back to the customer,
        // and we block any other staged actions (quotes, invoices, etc.)
        if ($state->escalated) {
            $this->sendOutboundReply($state, $state->finalDecision['reply_text'] ?? 'Let me confirm this with our team and follow up shortly.');
            $state->nodePath[] = 'action_execution';
            $state->latencyMs['action_execution'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        // 1. Execute all staged tools (PDFs, followups, emails)
        $executed = [];
        foreach ($state->toolCalls as &$toolCall) {
            if ($toolCall['status'] === 'staged') {
                try {
                    $name = $toolCall['name'];
                    $inputs = $toolCall['arguments'] ?? [];

                    // Dispatch via AgentToolService
                    $result = $this->agentTools->dispatch($name, $inputs, $state->traceId);
                    $toolCall['status'] = 'executed';
                    $toolCall['result'] = $result;

                    $state->actionsTaken[] = $name;
                } catch (\Throwable $e) {
                    $toolCall['status'] = 'failed';
                    $toolCall['result'] = ['error' => $e->getMessage()];
                    Log::channel('production')->error("Staged action [{$toolCall['name']}] execution failed", [
                        'trace_id' => $state->traceId,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
            $executed[] = $toolCall;
        }
        $state->toolCalls = $executed;

        // 2. Send the final reply text back to the customer
        $replyText = $state->finalDecision['reply_text'] ?? '';
        if (!empty($replyText)) {
            $this->sendOutboundReply($state, $replyText);
        }

        $state->nodePath[] = 'action_execution';
        $state->latencyMs['action_execution'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    private function sendOutboundReply(AgentState $state, string $text): void
    {
        $channel = $state->message?->channel ?? 'whatsapp';
        $sender = $state->message?->sender ?? '';

        if (empty($sender)) {
            return;
        }

        // Idempotency: check if an outbound message has already been sent for this trace_id
        $alreadySent = OutboundMessage::where('trace_id', $state->traceId)->exists();
        if ($alreadySent) {
            Log::channel('production')->info('Outbound reply skipped (idempotency key hit)', [
                'trace_id' => $state->traceId,
            ]);
            return;
        }

        // Execute send based on inbound channel type
        if ($channel === 'whatsapp') {
            $this->agentTools->dispatch('send_whatsapp_message', [
                'to'      => $sender,
                'message' => $text,
            ], $state->traceId);
        } elseif ($channel === 'email') {
            $this->agentTools->dispatch('send_email', [
                'to'      => $sender,
                'subject' => 'RE: ' . ($state->message->subject ?? 'Inquiry'),
                'body'    => $text,
            ], $state->traceId);
        }

        // Persist OutboundMessage record in DB
        OutboundMessage::create([
            'lead_id'   => $state->lead?->id,
            'trace_id'  => $state->traceId,
            'channel'   => $channel,
            'recipient' => $sender,
            'content'   => $text,
        ]);

        $state->actionsTaken[] = "send_reply:{$channel}";
    }
}
