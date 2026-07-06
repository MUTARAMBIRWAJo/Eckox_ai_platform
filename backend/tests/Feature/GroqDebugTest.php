<?php

namespace Tests\Feature;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\Product;
use App\Services\AI\AIDecisionEngine;
use App\Services\AI\AgentState;
use App\Services\AI\ResponseGuardrail;
use App\Services\AI\RetrievalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Debug test to capture exact Groq responses and guardrail failure points.
 * This test runs REAL Groq API calls (not mocked) and logs full debug output.
 */
class GroqDebugTest extends TestCase
{
    use RefreshDatabase;

    private Lead $lead;
    private Product $productX;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = Lead::create([
            'name'           => 'Debug Lead',
            'email'          => 'debug@lead.com',
            'status'         => 'new',
            'source_channel' => 'whatsapp',
            'phone'          => '+1234567890',
        ]);

        $this->productX = Product::create([
            'name'           => 'Eckox Processor X',
            'sku'            => 'SKU-PROC-X',
            'price_eur'      => 800.00,
            'price_usd'      => 900.00,
            'stock_level'    => 10,
            'spec_processor' => '8-core 3.5GHz',
            'spec_ram'       => '16GB',
            'spec_storage'   => '512GB SSD',
        ]);
    }

    public function test_groq_happy_path_with_debug_output(): void
    {
        $engine = app(AIDecisionEngine::class);

        $inboundMsg = InboundMessage::create([
            'lead_id'      => $this->lead->id,
            'direction'    => 'inbound',
            'channel'      => 'whatsapp',
            'content_text' => 'What is the price of the Eckox Processor X?',
            'processed_at' => null,
        ]);

        // Run decision engine (calls real Groq API)
        $decision = $engine->decide($this->lead, $inboundMsg);

        echo "\n==== GROQ DEBUG OUTPUT ====\n";
        echo "Decision: " . json_encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        echo "\n==== TRACE STATE ====\n";

        // If we have access to the state, log it
        if (method_exists($engine, 'lastState')) {
            $state = $engine->lastState();
            echo "Final Decision from state:\n";
            echo json_encode($state->finalDecision, JSON_PRETTY_PRINT) . "\n";
            echo "\nGuardrail Verdict:\n";
            echo json_encode($state->guardrailVerdict ?? 'NOT SET', JSON_PRETTY_PRINT) . "\n";
            echo "\nLLM Raw Response:\n";
            echo json_encode($state->llmRawResponse ?? 'NOT SET', JSON_PRETTY_PRINT) . "\n";
        }

        // Assert it should NOT be escalated for a valid, answerable question
        $this->assertNotEquals('escalate', $decision['decision'] ?? 'unknown',
            "Happy path should reply, not escalate. Full decision: " . json_encode($decision, JSON_PRETTY_PRINT));
    }

    public function test_groq_response_structure_directly(): void
    {
        $groq = app(\App\Services\AI\Providers\GroqProvider::class);
        $state = new AgentState('debug-trace-1');

        $messages = [
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You are a sales agent for Eckox Industrial. You have access to product information via tools.

IMPORTANT: Your response MUST be valid JSON in this exact structure:
{
  "decision": "reply" or "escalate",
  "reply_text": "Your response to the user",
  "confidence": 0.0 to 1.0,
  "cited_facts": [
    {
      "field": "price",
      "value": 900,
      "source": "product:SKU-PROC-X"
    }
  ]
}

Do not wrap your response in markdown code blocks. Return ONLY the JSON object.
PROMPT
            ],
            [
                'role' => 'user',
                'content' => 'What is the price of the Eckox Processor X in USD?'
            ]
        ];

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_price',
                    'description' => 'Get the price of a product in a specific region',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'sku' => ['type' => 'string', 'description' => 'Product SKU'],
                            'region' => ['type' => 'string', 'enum' => ['europe', 'africa']]
                        ],
                        'required' => ['sku', 'region']
                    ]
                ]
            ]
        ];

        $response = $groq->chat($messages, $tools, $state);

        echo "\n==== RAW GROQ RESPONSE ====\n";
        echo "Provider: " . $response['provider'] . "\n";
        echo "Model: " . $response['model'] . "\n";
        echo "Latency: " . $response['latency_ms'] . "ms\n";
        echo "\n---- Message Content ----\n";
        $content = $response['choice']['message']['content'];
        echo $content . "\n";
        echo "\n---- Message Structure ----\n";
        echo json_encode($response['choice']['message'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        // Try to parse and validate
        $decoded = json_decode($content, true);
        echo "\n---- Parsed JSON ----\n";
        if ($decoded) {
            echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
            echo "\nRequired fields present:\n";
            echo "  - decision: " . (isset($decoded['decision']) ? 'YES (' . $decoded['decision'] . ')' : 'NO') . "\n";
            echo "  - reply_text: " . (isset($decoded['reply_text']) ? 'YES' : 'NO') . "\n";
            echo "  - confidence: " . (isset($decoded['confidence']) ? 'YES (' . $decoded['confidence'] . ')' : 'NO') . "\n";
            echo "  - cited_facts: " . (isset($decoded['cited_facts']) ? 'YES (count: ' . count($decoded['cited_facts']) . ')' : 'NO') . "\n";
        } else {
            echo "FAILED TO PARSE JSON\n";
            echo "Error: " . json_last_error_msg() . "\n";
        }

        $this->assertTrue($decoded !== null && is_array($decoded),
            "Groq response should be valid JSON. Got: " . substr($content, 0, 200));
    }

    public function test_guardrail_validation_with_groq_response(): void
    {
        $guardrail = app(ResponseGuardrail::class);

        // Simulate what Groq actually returns
        $inboundText = 'What is the price of the Eckox Processor X?';

        $groqResponse = [
            'decision' => 'reply',
            'reply_text' => 'The Eckox Processor X costs $900 USD.',
            'confidence' => 0.95,
            'cited_facts' => [
                [
                    'field' => 'price',
                    'value' => 900,
                    'source' => 'product:SKU-PROC-X'
                ]
            ]
        ];

        $retrievalContext = new RetrievalContext(
            [$this->productX],
            [],
            'africa',  // Default region for demo
            'en'
        );

        echo "\n==== GUARDRAIL VALIDATION TEST ====\n";
        echo "Inbound: " . $inboundText . "\n";
        echo "Groq Response:\n";
        echo json_encode($groqResponse, JSON_PRETTY_PRINT) . "\n";

        try {
            $verdict = $guardrail->check($inboundText, $groqResponse, $retrievalContext);
            echo "\n✅ GUARDRAIL PASSED\n";
            echo json_encode($verdict, JSON_PRETTY_PRINT) . "\n";
            $this->assertTrue($verdict['valid'] ?? false);
        } catch (\Throwable $e) {
            echo "\n❌ GUARDRAIL FAILED\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $this->fail("Guardrail should pass for valid Groq response. Error: " . $e->getMessage());
        }
    }
}
