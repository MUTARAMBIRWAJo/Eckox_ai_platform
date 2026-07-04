<?php

namespace App\Listeners;

use App\Events\InboundMessageReceived;
use Illuminate\Support\Facades\Log;

class UpdateLeadOnInbound
{
    public function handle(InboundMessageReceived $event): void
    {
        $message = $event->message;
        $lead    = $message->lead;

        if ($lead) {
            $lead->update(['last_message_at' => now()]);
        }

        Log::channel('production')->info('Inbound message event received', [
            'channel'    => $message->channel,
            'lead_id'    => $lead?->id,
            'message_id' => $message->id,
        ]);
    }
}
