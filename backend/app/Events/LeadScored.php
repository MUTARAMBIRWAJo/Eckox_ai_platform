<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadScored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $score,   // 'hot' | 'warm' | 'cold'
        public readonly string $region,
        public readonly string $traceId,
    ) {}
}
