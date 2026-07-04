<?php

namespace App\Services\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiKey;
    private string $baseUrl;
    private string $phoneNumberId;

    public function __construct()
    {
        $this->apiKey        = config('services.whatsapp.api_key', '');
        $this->baseUrl       = config('services.whatsapp.base_url', 'https://waba.360dialog.io/v1');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    /**
     * Send a plain text WhatsApp message via 360Dialog API.
     */
    public function sendText(string $to, string $message, string $traceId = ''): bool
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->sanitizePhone($to),
            'type'              => 'text',
            'text'              => ['body' => $message],
        ];

        return $this->dispatch($payload, $to, $traceId);
    }

    /**
     * Send a document (PDF) via WhatsApp.
     */
    public function sendDocument(string $to, string $fileUrl, string $caption, string $traceId = ''): bool
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->sanitizePhone($to),
            'type'              => 'document',
            'document'          => [
                'link'     => $fileUrl,
                'caption'  => $caption,
                'filename' => 'eckox_document.pdf',
            ],
        ];

        return $this->dispatch($payload, $to, $traceId);
    }

    /**
     * Parse an inbound webhook payload from 360Dialog.
     * Returns normalized message array or null if not a user message.
     */
    public function parseWebhook(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $change  = $entry['changes'][0] ?? null;
        $message = $change['value']['messages'][0] ?? null;

        if (!$message) {
            return null;
        }

        $contact = $change['value']['contacts'][0] ?? null;

        return [
            'sender'    => $message['from'] ?? '',
            'name'      => $contact['profile']['name'] ?? 'Unknown',
            'content'   => $message['text']['body'] ?? '',
            'channel'   => 'whatsapp',
            'timestamp' => $message['timestamp'] ?? now()->timestamp,
            'metadata'  => [
                'message_id' => $message['id'] ?? null,
                'type'       => $message['type'] ?? 'text',
            ],
        ];
    }

    private function dispatch(array $payload, string $to, string $traceId): bool
    {
        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", $payload);

            if ($response->successful()) {
                Log::channel('production')->info('WhatsApp message sent', [
                    'to'       => $to,
                    'trace_id' => $traceId,
                    'status'   => $response->status(),
                ]);
                return true;
            }

            Log::channel('production')->error('WhatsApp send failed', [
                'to'       => $to,
                'trace_id' => $traceId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::channel('production')->error('WhatsApp send exception', [
                'to'        => $to,
                'trace_id'  => $traceId,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function sanitizePhone(string $phone): string
    {
        // Strip non-numeric characters except leading +
        return preg_replace('/[^\d+]/', '', $phone);
    }
}
