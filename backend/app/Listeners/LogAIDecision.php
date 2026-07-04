<?php

namespace App\Listeners;

use App\Events\AIDecisionGenerated;
use Illuminate\Support\Facades\Log;

class LogAIDecision
{
    public function handle(AIDecisionGenerated $event): void
    {
        $decision = $event->decision;

        Log::channel('production')->info('AI decision recorded', [
            'trace_id'      => $decision->trace_id,
            'intent'        => $decision->intent,
            'decision_type' => $decision->decision_type,
            'confidence'    => $decision->confidence,
            'region'        => $decision->region,
            'lead_id'       => $decision->lead_id,
        ]);
    }
}
