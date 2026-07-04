<?php

namespace App\Listeners;

use App\Events\ActionExecuted;
use Illuminate\Support\Facades\Log;

class LogActionExecuted
{
    public function handle(ActionExecuted $event): void
    {
        Log::channel('production')->info('Action executed', [
            'trace_id'    => $event->traceId,
            'action_type' => $event->actionType,
            'channel'     => $event->channel,
            'lead_id'     => $event->leadId,
            'metadata'    => $event->metadata,
        ]);
    }
}
