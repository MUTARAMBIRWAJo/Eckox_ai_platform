<?php

namespace Tests\Feature;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Services\AI\AIDecisionEngine;
use App\Services\AI\EscalationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class EscalationGuardTest extends TestCase
{
    use RefreshDatabase;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lead = Lead::create([
            'name' => 'John Client',
            'email' => 'john@client.com',
            'status' => 'new',
            'source_channel' => 'email',
        ]);
    }

    /**
     * Scenario 1: Regex catches it (e.g. 'lawyer' match) and overrides low-confidence LLM
     */
    public function test_escalates_when_regex_flags_even_if_llm_says_no(): void
    {
        // Mock OpenAI response stating no escalation is needed
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'requires_human_escalation' => false,
                                'reason' => 'Seems fine',
                                'confidence' => 0.2
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $message = InboundMessage::create([
            'channel' => 'email',
            'sender' => 'john@client.com',
            'content' => 'I am going to get my lawyer involved now.',
            'lead_id' => $this->lead->id,
        ]);

        $engine = app(AIDecisionEngine::class);
        $decision = $engine->analyse($message, $this->lead);

        $this->assertEquals('escalate', $decision->decision_type);
        $this->assertEquals('complaint_legal', $decision->intent);
    }

    /**
     * Scenario 2: Regex misses (implicit threat without keyword match) but LLM correctly flags it
     */
    public function test_escalates_when_llm_flags_even_if_regex_misses(): void
    {
        // Mock OpenAI response stating escalation is needed
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'requires_human_escalation' => true,
                                'reason' => 'Implied litigation threat detected',
                                'confidence' => 0.95
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $message = InboundMessage::create([
            'channel' => 'email',
            'sender' => 'john@client.com',
            'content' => 'I will be taking this case further to settle things.',
            'lead_id' => $this->lead->id,
        ]);

        $engine = app(AIDecisionEngine::class);
        $decision = $engine->analyse($message, $this->lead);

        $this->assertEquals('escalate', $decision->decision_type);
        $this->assertEquals('escalated_by_llm', $decision->intent);
    }

    /**
     * Scenario 3: Malformed LLM response defaults to escalation (fail-safe)
     */
    public function test_escalates_on_malformed_llm_json_fallback(): void
    {
        // Mock OpenAI response with malformed JSON structure
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{broken-json-response'
                        ]
                    ]
                ]
            ])
        ]);

        $message = InboundMessage::create([
            'channel' => 'email',
            'sender' => 'john@client.com',
            'content' => 'Please update my account info.',
            'lead_id' => $this->lead->id,
        ]);

        $engine = app(AIDecisionEngine::class);
        $decision = $engine->analyse($message, $this->lead);

        $this->assertEquals('escalate', $decision->decision_type);
        $this->assertStringContainsString('fallback', $decision->response['reason']);
    }

    /**
     * Scenario 4: Benign messages do not trigger escalation
     */
    public function test_does_not_escalate_benign_messages(): void
    {
        OpenAI::fake([
            // First mock call: EscalationGuard
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'requires_human_escalation' => false,
                                'reason' => 'Normal product query',
                                'confidence' => 0.99
                            ])
                        ]
                    ]
                ]
            ]),
            // Second mock call: Decision completion (reply payload)
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'intent' => 'buy_intent',
                                'decision' => 'reply',
                                'confidence' => 0.9,
                                'reply' => 'Hello, pricing starts at 50 USD.',
                                'document_required' => null,
                                'escalate' => false,
                                'currency' => 'USD',
                                'payment_method' => 'Mobile Money / Flutterwave',
                                'region' => 'africa',
                                'ai_score' => 'warm'
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $message = InboundMessage::create([
            'channel' => 'email',
            'sender' => 'john@client.com',
            'content' => 'What is the pricing for the services?',
            'lead_id' => $this->lead->id,
        ]);

        $engine = app(AIDecisionEngine::class);
        $decision = $engine->analyse($message, $this->lead);

        $this->assertEquals('reply', $decision->decision_type);
        $this->assertEquals('buy_intent', $decision->intent);
    }
}
