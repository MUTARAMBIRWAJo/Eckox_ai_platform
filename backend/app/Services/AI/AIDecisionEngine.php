<?php

namespace App\Services\AI;

use App\Events\AIDecisionGenerated;
use App\Events\LeadScored;
use App\Models\AiDecision;
use App\Models\InboundMessage;
use App\Models\Lead;
use Illuminate\Support\Str;

class AIDecisionEngine
{
    public function __construct(
        private readonly EckoxAgentOrchestrator $orchestrator
    ) {}

    /**
     * Primary entry point: delegates entirely to the multi-node Agent Graph Orchestrator.
     */
    public function analyse(InboundMessage $message, ?Lead $lead = null): AiDecision
    {
        $state = $this->orchestrator->run($message, $lead);

        // Retrieve the decision record produced by the graph or create one
        $decision = AiDecision::where('trace_id', $state->traceId)->first();

        if (!$decision) {
            $decision = AiDecision::create([
                'id'            => (string) Str::uuid(),
                'lead_id'       => $lead?->id,
                'trace_id'      => $state->traceId,
                'intent'        => $state->intent,
                'region'        => $state->region,
                'decision_type' => $state->finalDecision['decision'] ?? 'reply',
                'confidence'    => $state->finalDecision['confidence'] ?? 0.5,
                'prompt'        => $state->promptPayload,
                'response'      => $state->finalDecision,
            ]);

            event(new AIDecisionGenerated($decision));
        }

        // Update lead scoring in CRM
        if ($lead && isset($state->finalDecision['ai_score'])) {
            $lead->update([
                'ai_score'        => $state->finalDecision['ai_score'],
                'region'          => $state->region,
                'language'        => $state->language,
                'last_message_at' => now(),
            ]);

            event(new LeadScored($lead, $state->finalDecision['ai_score'], $state->region, $state->traceId));
        }

        return $decision;
    }
}
