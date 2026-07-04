<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActionExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $traceId,
        public readonly string $actionType,  // 'whatsapp_sent', 'email_sent', 'pdf_generated'
        public readonly string $channel,
        public readonly ?string $leadId,
        public readonly array  $metadata = [],
    ) {}
}
