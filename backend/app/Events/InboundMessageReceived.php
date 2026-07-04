<?php

namespace App\Events;

use App\Models\InboundMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboundMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InboundMessage $message,
    ) {}
}
