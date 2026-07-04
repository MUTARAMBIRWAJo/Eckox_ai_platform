<?php

namespace App\Jobs;

use App\Events\ActionExecuted;
use App\Models\OutboundMessage;
use App\Services\Channels\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 20;
    public int $backoff = 5;

    public function __construct(
        public readonly string  $to,
        public readonly string  $message,
        public readonly string  $traceId  = '',
        public readonly ?string $leadId   = null,
        public readonly ?string $fileUrl  = null,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $record = OutboundMessage::create([
            'id'        => (string) Str::uuid(),
            'lead_id'   => $this->leadId,
            'channel'   => 'whatsapp',
            'recipient' => $this->to,
            'content'   => $this->message,
            'status'    => 'queued',
            'trace_id'  => $this->traceId,
        ]);

        $success = $this->fileUrl
            ? $whatsApp->sendDocument($this->to, $this->fileUrl, $this->message, $this->traceId)
            : $whatsApp->sendText($this->to, $this->message, $this->traceId);

        $record->update(['status' => $success ? 'sent' : 'failed']);

        if ($success) {
            event(new ActionExecuted(
                traceId:    $this->traceId,
                actionType: 'whatsapp_sent',
                channel:    'whatsapp',
                leadId:     $this->leadId,
                metadata:   ['to' => $this->to],
            ));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('SendWhatsAppJob failed', [
            'to'        => $this->to,
            'trace_id'  => $this->traceId,
            'exception' => $exception->getMessage(),
        ]);

        OutboundMessage::where('trace_id', $this->traceId)
            ->where('channel', 'whatsapp')
            ->update(['status' => 'failed']);
    }
}
