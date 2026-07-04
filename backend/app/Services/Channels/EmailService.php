<?php

namespace App\Services\Channels;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class EmailService
{
    /**
     * Send a plain-text / HTML email reply.
     */
    public function sendReply(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath = null,
        string $traceId = '',
    ): bool {
        try {
            Mail::raw($body, function (Message $message) use ($to, $subject, $attachmentPath) {
                $message->to($to)
                        ->subject($subject)
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name', 'Eckox AI Platform'),
                        );

                if ($attachmentPath && file_exists($attachmentPath)) {
                    $message->attach($attachmentPath);
                }
            });

            Log::channel('production')->info('Email reply sent', [
                'to'       => $to,
                'subject'  => $subject,
                'trace_id' => $traceId,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::channel('production')->error('Email send failed', [
                'to'        => $to,
                'trace_id'  => $traceId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse an inbound email webhook payload (e.g. from Mailgun, SendGrid inbound parse, or IMAP hook).
     * Returns normalized message array.
     */
    public function parseInbound(array $payload): ?array
    {
        // Normalize across common inbound email webhook providers
        $sender  = $payload['sender'] ?? $payload['from'] ?? null;
        $content = $payload['stripped-text'] ?? $payload['text'] ?? $payload['body-plain'] ?? null;
        $subject = $payload['subject'] ?? '(no subject)';

        if (!$sender || !$content) {
            return null;
        }

        // Extract sender email if wrapped in "Name <email>" format
        if (preg_match('/<(.+?)>/', $sender, $matches)) {
            $sender = $matches[1];
        }

        return [
            'sender'  => trim($sender),
            'content' => trim($content),
            'channel' => 'email',
            'subject' => $subject,
            'metadata' => [
                'message_id'  => $payload['Message-Id'] ?? $payload['message-id'] ?? null,
                'subject'     => $subject,
                'recipient'   => $payload['recipient'] ?? null,
            ],
        ];
    }
}
