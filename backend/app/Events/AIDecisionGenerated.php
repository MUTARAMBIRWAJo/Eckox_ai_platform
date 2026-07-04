<?php

namespace App\Events;

use App\Models\AiDecision;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AIDecisionGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AiDecision $decision,
    ) {}
}
