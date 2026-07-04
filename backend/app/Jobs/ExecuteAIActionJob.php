<?php

namespace App\Jobs;

use App\Models\AiDecision;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ExecuteAIActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $decisionId,
        public readonly string $traceId,
    ) {}

    public function handle(): void
    {
        $decision = AiDecision::findOrFail($this->decisionId);
        $response = $decision->response ?? [];
        $lead     = $decision->lead_id ? Lead::find($decision->lead_id) : null;

        $decisionType = $decision->decision_type;
        $channel      = $lead?->source_channel ?? 'email';
        $recipient    = $channel === 'whatsapp' ? ($lead?->phone ?? '') : ($lead?->email ?? '');
        $reply        = $response['reply'] ?? '';
        $region       = $decision->region ?? 'africa';

        Log::channel('production')->info('Executing AI action', [
            'decision'  => $decisionType,
            'channel'   => $channel,
            'recipient' => $recipient,
            'trace_id'  => $this->traceId,
        ]);

        // Route decision to appropriate workers
        match ($decisionType) {
            'reply' => $this->dispatchReply($channel, $recipient, $reply),

            'generate_quote' => $this->dispatchReply($channel, $recipient, $reply, fn () =>
                $lead ? GenerateDocumentJob::dispatch($lead->id, 'quote', $response, $region, $this->traceId)
                              ->onQueue('pdf-generation') : null
            ),

            'generate_invoice' => $this->dispatchReply($channel, $recipient, $reply, fn () =>
                $lead ? GenerateDocumentJob::dispatch($lead->id, 'invoice', $response, $region, $this->traceId)
                              ->onQueue('pdf-generation') : null
            ),

            'escalate' => $this->handleEscalation($lead, $reply, $channel, $recipient),

            'ask_clarification' => $this->dispatchReply($channel, $recipient, $reply),

            default => Log::channel('production')->warning('Unknown decision type', [
                'decision' => $decisionType,
                'trace_id' => $this->traceId,
            ]),
        };
    }

    private function dispatchReply(
        string $channel,
        string $recipient,
        string $content,
        ?\Closure $callback = null,
    ): void {
        if ($channel === 'whatsapp') {
            SendWhatsAppJob::dispatch($recipient, $content, $this->traceId)
                ->onQueue('message-outbound');
        } else {
            SendEmailJob::dispatch($recipient, 'Eckox AI — Your Request', $content, null, $this->traceId)
                ->onQueue('message-outbound');
        }

        if ($callback) {
            $callback();
        }
    }

    private function handleEscalation(?Lead $lead, string $reply, string $channel, string $recipient): void
    {
        // Send acknowledgement to user
        $this->dispatchReply($channel, $recipient, $reply);

        // Notify admin team via email
        $adminEmail = config('app.escalation_email', 'admin@eckox.ai');
        SendEmailJob::dispatch(
            $adminEmail,
            '🚨 AI Escalation Alert — Lead: ' . ($lead?->name ?? 'Unknown'),
            "An AI escalation has been triggered.\n\nLead: {$lead?->name}\nEmail: {$lead?->email}\nTrace ID: {$this->traceId}",
            null,
            $this->traceId
        )->onQueue('message-outbound');

        Log::channel('production')->warning('Lead escalated to human', [
            'lead_id'  => $lead?->id,
            'trace_id' => $this->traceId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('ExecuteAIActionJob failed', [
            'decision_id' => $this->decisionId,
            'trace_id'    => $this->traceId,
            'exception'   => $exception->getMessage(),
        ]);
    }
}
