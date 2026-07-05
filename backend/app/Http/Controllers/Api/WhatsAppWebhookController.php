<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessageJob;
use App\Services\Channels\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsApp,
    ) {}

    /**
     * Handle 360Dialog verification challenge.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive inbound WhatsApp messages.
     */
    public function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        $traceId = (string) Str::uuid();

        Log::channel('production')->info('WhatsApp webhook received', [
            'trace_id' => $traceId,
            'ip'       => $request->ip(),
        ]);

        $signature = $request->header('X-360Dialog-Signature');
        $secret    = config('services.whatsapp.platform_secret') ?: env('WHATSAPP_PLATFORM_SECRET');

        if ($secret) {
            $computed = hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($computed, (string) $signature)) {
                Log::channel('production')->warning('WhatsApp webhook signature mismatch', [
                    'computed' => $computed,
                    'header'   => $signature,
                    'trace_id' => $traceId,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();

        // Parse normalized message from webhook payload
        $parsed = $this->whatsApp->parseWebhook($payload);

        if (!$parsed) {
            // Non-message event (delivery receipts, read receipts, etc.)
            return response()->json(['status' => 'ignored']);
        }

        if (empty($parsed['content'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'empty_content']);
        }

        // Detect country from phone number prefix
        $country = $this->detectCountryFromPhone($parsed['sender']);

        // Dispatch to high-priority inbound queue
        ProcessInboundMessageJob::dispatch(
            channel:  'whatsapp',
            sender:   $parsed['sender'],
            content:  $parsed['content'],
            metadata: array_merge($parsed['metadata'] ?? [], [
                'name'    => $parsed['name'] ?? 'Unknown',
                'country' => $country,
            ]),
            traceId: $traceId,
        )->onQueue('inbound-processing');

        return response()->json(['status' => 'accepted', 'trace_id' => $traceId]);
    }

    /**
     * Heuristic country detection from E.164 phone prefix.
     */
    private function detectCountryFromPhone(string $phone): ?string
    {
        $prefixMap = [
            '+234' => 'NG', '+233' => 'GH', '+254' => 'KE',
            '+221' => 'SN', '+225' => 'CI', '+237' => 'CM',
            '+212' => 'MA', '+213' => 'DZ', '+216' => 'TN',
            '+20'  => 'EG', '+27'  => 'ZA', '+255' => 'TZ',
            '+33'  => 'FR', '+49'  => 'DE', '+39'  => 'IT',
            '+34'  => 'ES', '+351' => 'PT', '+32'  => 'BE',
            '+31'  => 'NL', '+44'  => 'GB', '+41'  => 'CH',
        ];

        foreach ($prefixMap as $prefix => $code) {
            if (str_starts_with($phone, $prefix)) {
                return $code;
            }
        }

        return null;
    }
}
