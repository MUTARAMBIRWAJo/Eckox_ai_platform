<?php

namespace App\Services\AI;

interface AgentNode
{
    /**
     * Process the current state and return the updated state.
     */
    public function handle(AgentState $state): AgentState;
}
