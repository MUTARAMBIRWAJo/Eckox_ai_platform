<?php

namespace App\Jobs;

use App\Events\ActionExecuted;
use App\Models\Lead;
use App\Services\Documents\DocumentGenerationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $type,    // 'quote' | 'invoice' | 'certificate'
        public readonly array   $data,
        public readonly string  $region,
        public readonly string  $traceId,
        public readonly ?string $sendToChannel = null, // send PDF after generation
        public readonly ?string $recipient     = null,
    ) {}

    public function handle(DocumentGenerationEngine $engine): void
    {
        $lead = Lead::findOrFail($this->leadId);

        Log::channel('production')->info('Generating document', [
            'type'     => $this->type,
            'region'   => $this->region,
            'lead_id'  => $this->leadId,
            'trace_id' => $this->traceId,
        ]);

        $document = match ($this->type) {
            'quote'       => $engine->generateQuote($lead, $this->data, $this->region, $this->traceId),
            'invoice'     => $engine->generateInvoice($lead, $this->data, $this->region, $this->traceId),
            'certificate' => $engine->generateCertificate($lead, $this->data, $this->traceId),
            default       => throw new \InvalidArgumentException("Unknown document type: {$this->type}"),
        };

        event(new ActionExecuted(
            traceId:    $this->traceId,
            actionType: 'pdf_generated',
            channel:    $this->sendToChannel ?? 'email',
            leadId:     $this->leadId,
            metadata:   ['document_id' => $document->id, 'type' => $this->type],
        ));

        // If we need to send the PDF via a channel after generation
        if ($this->sendToChannel && $this->recipient) {
            $caption = ucfirst($this->type) . ' from Eckox AI Platform';
            if ($this->sendToChannel === 'whatsapp') {
                SendWhatsAppJob::dispatch($this->recipient, $caption, $this->traceId, $this->leadId, $document->file_url)
                    ->onQueue('message-outbound');
            } else {
                SendEmailJob::dispatch($this->recipient, "Your {$caption}", $caption, $document->file_url, $this->traceId, $this->leadId)
                    ->onQueue('message-outbound');
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('GenerateDocumentJob failed', [
            'type'      => $this->type,
            'lead_id'   => $this->leadId,
            'trace_id'  => $this->traceId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
