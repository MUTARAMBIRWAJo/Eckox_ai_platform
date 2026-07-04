<?php

namespace App\Jobs;

use App\Events\ActionExecuted;
use App\Models\OutboundMessage;
use App\Services\Channels\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;
    public int $backoff = 10;

    public function __construct(
        public readonly string  $to,
        public readonly string  $subject,
        public readonly string  $body,
        public readonly ?string $attachmentPath = null,
        public readonly string  $traceId        = '',
        public readonly ?string $leadId         = null,
    ) {}

    public function handle(EmailService $email): void
    {
        $record = OutboundMessage::create([
            'id'        => (string) Str::uuid(),
            'lead_id'   => $this->leadId,
            'channel'   => 'email',
            'recipient' => $this->to,
            'content'   => $this->body,
            'status'    => 'queued',
            'trace_id'  => $this->traceId,
        ]);

        $success = $email->sendReply(
            to:             $this->to,
            subject:        $this->subject,
            body:           $this->body,
            attachmentPath: $this->attachmentPath,
            traceId:        $this->traceId,
        );

        $record->update(['status' => $success ? 'sent' : 'failed']);

        if ($success) {
            event(new ActionExecuted(
                traceId:    $this->traceId,
                actionType: 'email_sent',
                channel:    'email',
                leadId:     $this->leadId,
                metadata:   ['to' => $this->to, 'subject' => $this->subject],
            ));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('SendEmailJob failed', [
            'to'        => $this->to,
            'trace_id'  => $this->traceId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
