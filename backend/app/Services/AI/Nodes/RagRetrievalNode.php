<?php

namespace App\Services\AI\Nodes;

use App\Services\AI\AgentNode;
use App\Services\AI\AgentState;
use App\Services\AI\RetrievalContext;

class RagRetrievalNode implements AgentNode
{
    public function handle(AgentState $state): AgentState
    {
        $startedAt = microtime(true);

        if ($state->escalated) {
            $state->nodePath[] = 'rag_retrieval';
            $state->latencyMs['rag_retrieval'] = (int) round((microtime(true) - $startedAt) * 1000);
            return $state;
        }

        $content = $state->message?->content ?? '';
        $redacted = $this->redactPII($content);

        // Call the exact KB-only semantic/substring retrieval logic
        $context = RetrievalContext::buildKbOnly($redacted, $state->region, $state->language);
        $state->retrievalContext = $context->toLLMContext();

        $state->nodePath[] = 'rag_retrieval';
        $state->latencyMs['rag_retrieval'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $state;
    }

    private function redactPII(string $text): string
    {
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text);
        $text = preg_replace('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{1,4}\)?(?:[-.\s]?\d{1,4}){3,6}/', '[REDACTED_PHONE]', $text);
        return $text;
    }
}
