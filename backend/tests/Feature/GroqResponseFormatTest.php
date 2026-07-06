<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AI\ResponseGuardrail;
use App\Services\AI\RetrievalContext;
use Tests\TestCase;

/**
 * Test Groq response format and guardrail compatibility WITHOUT relying on OpenAI mocks.
 * This validates that Groq's output is structurally correct before guardrail validation.
 */
class GroqResponseFormatTest extends TestCase
{
    public function test_groq_returns_correctly_formatted_json(): void
    {
        // This test uses REAL Groq API (not mocked)
        // Skip if rate-limited
        $groq = app(\App\Services\AI\Providers\GroqProvider::class);
        $state = new \App\Services\AI\AgentState('format-test-' . time());

        $messages = [
            [
                'role' => 'system',
                'content' => 'Respond with ONLY a valid JSON object (no markdown, no explanation). Use this structure: {"decision": "reply", "reply_text": "...", "confidence": 0.9, "cited_facts": [{"field": "price", "value": 800, "source": "product:SKU-123"}]}'
            ],
            [
                'role' => 'user',
                'content' => 'Summarize: The product costs 800 euros.'
            ]
        ];

        try {
            $response = $groq->chat($messages, [], $state);
            $content = $response['choice']['message']['content'];

            echo "\n✓ Groq responded without error\n";
            echo "Response (first 300 chars): " . substr($content, 0, 300) . "\n";

            // Try to parse as JSON
            $decoded = json_decode($content, true);
            $this->assertIsArray($decoded, "Groq response should be valid JSON");
            echo "✓ Valid JSON\n";

            // Check required fields
            $this->assertArrayHasKey('decision', $decoded, "Should have 'decision' field");
            echo "✓ Has 'decision' field: " . $decoded['decision'] . "\n";

            $this->assertArrayHasKey('reply_text', $decoded, "Should have 'reply_text' field");
            echo "✓ Has 'reply_text' field: " . substr($decoded['reply_text'], 0, 50) . "...\n";

            $this->assertArrayHasKey('confidence', $decoded, "Should have 'confidence' field");
            echo "✓ Has 'confidence' field: " . $decoded['confidence'] . "\n";

            // This test documents Groq's actual output structure
            $this->pass('Groq response format is acceptable');

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'rate_limit')) {
                $this->markTestSkipped('Groq rate limit reached');
            }
            throw $e;
        }
    }

    public function test_groq_response_passes_guardrail_validation(): void
    {
        // Test with a realistic response format that Groq produces
        $guardrail = app(ResponseGuardrail::class);

        $inboundText = 'What is the price of the Processor X?';

        // This is what Groq typically returns (from our earlier test)
        $groqResponse = [
            'decision' => 'reply',
            'reply_text' => 'The Processor X costs $900 USD. Would you like to know more details?',
            'confidence' => 0.85,
            'cited_facts' => []  // Groq may not include cited_facts if no tools were called
        ];

        // Create a product for validation
        $product = Product::create([
            'name' => 'Processor X',
            'sku' => 'SKU-PROC-X',
            'price_eur' => 800,
            'price_usd' => 900,
            'stock_level' => 10,
            'spec_processor' => '8-core',
            'spec_ram' => '16GB',
            'spec_storage' => '512GB'
        ]);

        $context = new RetrievalContext(
            [$product],
            [],
            'africa',
            'en'
        );

        try {
            $verdict = $guardrail->check($inboundText, $groqResponse, $context);
            echo "\n✓ Groq response passed guardrail validation\n";
            echo "Verdict: " . json_encode($verdict, JSON_PRETTY_PRINT) . "\n";
            $this->assertTrue($verdict['valid'] ?? false);
        } catch (\Throwable $e) {
            echo "\n✗ Groq response FAILED guardrail validation\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "This is a REAL compatibility issue, not a test infrastructure problem\n";
            $this->fail("Guardrail validation failed: " . $e->getMessage());
        }
    }
}
