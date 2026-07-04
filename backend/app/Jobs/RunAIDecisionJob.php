<?php

namespace App\Jobs;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Services\AI\AIDecisionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAIDecisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly string  $messageId,
        public readonly ?string $leadId,
        public readonly string  $traceId,
    ) {}

    public function handle(AIDecisionEngine $engine): void
    {
        $message = InboundMessage::findOrFail($this->messageId);
        $lead    = $this->leadId ? Lead::find($this->leadId) : null;

        Log::channel('production')->info('Running AI decision', [
            'message_id' => $this->messageId,
            'lead_id'    => $this->leadId,
            'trace_id'   => $this->traceId,
        ]);

        // AI Decision Engine runs — emits AIDecisionGenerated event internally
        $decision = $engine->analyse($message, $lead);

        // Dispatch execution job to handle the decision
        ExecuteAIActionJob::dispatch($decision->id, $this->traceId)
            ->onQueue('message-outbound');
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('RunAIDecisionJob failed', [
            'message_id' => $this->messageId,
            'trace_id'   => $this->traceId,
            'exception'  => $exception->getMessage(),
        ]);
    }
}
