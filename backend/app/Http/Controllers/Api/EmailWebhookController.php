<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessageJob;
use App\Services\Channels\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailWebhookController extends Controller
{
    public function __construct(
        private readonly EmailService $email,
    ) {}

    /**
     * Receive inbound email from mail provider webhook (Mailgun, SendGrid, Postmark).
     */
    public function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        $traceId = (string) Str::uuid();

        Log::channel('production')->info('Email webhook received', [
            'trace_id' => $traceId,
            'ip'       => $request->ip(),
        ]);

        $parsed = $this->email->parseInbound($request->all());

        if (!$parsed) {
            return response()->json(['status' => 'ignored', 'reason' => 'parse_failed']);
        }

        if (empty($parsed['content'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'empty_content']);
        }

        // Detect country from sender domain TLD heuristic
        $country = $this->detectCountryFromEmail($parsed['sender']);

        ProcessInboundMessageJob::dispatch(
            channel:  'email',
            sender:   $parsed['sender'],
            content:  $parsed['content'],
            metadata: array_merge($parsed['metadata'] ?? [], [
                'country' => $country,
                'subject' => $parsed['subject'] ?? '',
            ]),
            traceId: $traceId,
        )->onQueue('inbound-processing');

        return response()->json(['status' => 'accepted', 'trace_id' => $traceId]);
    }

    /**
     * Heuristic country detection from email domain TLD.
     */
    private function detectCountryFromEmail(string $email): ?string
    {
        $tldMap = [
            '.ng' => 'NG', '.gh' => 'GH', '.ke' => 'KE',
            '.sn' => 'SN', '.ci' => 'CI', '.cm' => 'CM',
            '.ma' => 'MA', '.dz' => 'DZ', '.tn' => 'TN',
            '.eg' => 'EG', '.za' => 'ZA', '.tz' => 'TZ',
            '.fr' => 'FR', '.de' => 'DE', '.it' => 'IT',
            '.es' => 'ES', '.pt' => 'PT', '.be' => 'BE',
            '.nl' => 'NL', '.uk' => 'GB', '.ch' => 'CH',
        ];

        foreach ($tldMap as $tld => $code) {
            if (str_contains($email, $tld)) {
                return $code;
            }
        }

        return null;
    }
}
