<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\Lead;
use App\Models\Product;
use App\Services\AI\AgentToolService;
use App\Services\Documents\DocumentGenerationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layer 7 — Tool-Calling Architecture Tests
 *
 * Each test specifically tries to break the no-hallucination guarantee
 * from the tool-calling layer's perspective.
 */
class AgentToolServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentToolService $tools;
    private Lead $lead;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = Lead::create([
            'name'           => 'Tool Test Lead',
            'email'          => 'tool@test.com',
            'status'         => 'new',
            'source_channel' => 'email',
        ]);

        $this->product = Product::create([
            'name'           => 'Eckox Processor X',
            'sku'            => 'SKU-PROC-X',
            'price_eur'      => 800.00,
            'price_usd'      => 900.00,
            'stock_level'    => 15,
            'spec_processor' => '8-core 3.5GHz',
            'spec_ram'       => '16GB',
            'spec_storage'   => '512GB SSD',
        ]);

        KnowledgeBase::create([
            'region'           => 'europe',
            'doc_type'         => 'compliance',
            'product_category' => 'hardware',
            'content'          => 'Eckox Processor X complies with CE marking, ISO 17025, and GDPR.',
            'is_active'        => true,
        ]);

        KnowledgeBase::create([
            'region'    => 'africa',
            'doc_type'  => 'sla',
            'content'   => 'Hardware delivery SLA inside Africa is 15 business days.',
            'is_active' => true,
        ]);

        // Inactive entry — must NEVER surface in tool results
        KnowledgeBase::create([
            'region'    => 'europe',
            'doc_type'  => 'compliance',
            'content'   => 'STALE: Old compliance data — do not use.',
            'is_active' => false,
        ]);

        $fakeQuoteDoc   = new \App\Models\Document(['file_url' => '/tmp/fake-quote.pdf']);
        $fakeInvoiceDoc = new \App\Models\Document(['file_url' => '/tmp/fake-invoice.pdf']);

        $docEngineMock = $this->createMock(DocumentGenerationEngine::class);
        $docEngineMock->method('generateQuote')->willReturn($fakeQuoteDoc);
        $docEngineMock->method('generateInvoice')->willReturn($fakeInvoiceDoc);

        $this->tools = new AgentToolService($docEngineMock);
    }

    // =========================================================================
    // Adversarial Test 1: Unknown SKU — tool must NOT invent a price
    // =========================================================================

    public function test_get_product_price_returns_not_found_for_unknown_sku(): void
    {
        $result = $this->tools->dispatch('get_product_price', [
            'sku'    => 'SKU-DOES-NOT-EXIST',
            'region' => 'europe',
        ], 'trace-001');

        $this->assertFalse($result['found'], 'Tool must not return found=true for unknown SKU');
        $this->assertArrayNotHasKey('price', $result, 'Tool must not include a price field when product is unknown');
        $this->assertStringContainsString('SKU-DOES-NOT-EXIST', $result['message']);
    }

    // =========================================================================
    // Adversarial Test 2: Known SKU — correct price per region, no rounding
    // =========================================================================

    public function test_get_product_price_returns_exact_db_price_for_europe(): void
    {
        $result = $this->tools->dispatch('get_product_price', [
            'sku'    => 'SKU-PROC-X',
            'region' => 'europe',
        ], 'trace-002');

        $this->assertTrue($result['found']);
        $this->assertEquals(800.00, $result['price']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('product:SKU-PROC-X', $result['source']);
    }

    public function test_get_product_price_returns_usd_for_africa(): void
    {
        $result = $this->tools->dispatch('get_product_price', [
            'sku'    => 'SKU-PROC-X',
            'region' => 'africa',
        ], 'trace-003');

        $this->assertTrue($result['found']);
        $this->assertEquals(900.00, $result['price']);
        $this->assertEquals('USD', $result['currency']);
    }

    // =========================================================================
    // Adversarial Test 3: Stock check for unknown product
    // =========================================================================

    public function test_check_stock_returns_not_found_for_unknown_sku(): void
    {
        $result = $this->tools->dispatch('check_stock', ['sku' => 'GHOST-SKU'], 'trace-004');

        $this->assertFalse($result['found']);
        $this->assertFalse($result['in_stock']);
        $this->assertEquals(0, $result['stock_level']);
    }

    public function test_check_stock_returns_exact_db_level_for_known_sku(): void
    {
        $result = $this->tools->dispatch('check_stock', ['sku' => 'SKU-PROC-X'], 'trace-005');

        $this->assertTrue($result['found']);
        $this->assertTrue($result['in_stock']);
        $this->assertEquals(15, $result['stock_level']);
    }

    // =========================================================================
    // Adversarial Test 4: Compliance doc — inactive entries must not surface
    // =========================================================================

    public function test_get_compliance_doc_excludes_inactive_kb_entries(): void
    {
        $result = $this->tools->dispatch('get_compliance_doc', [
            'region'   => 'europe',
            'doc_type' => 'compliance',
        ], 'trace-006');

        $this->assertTrue($result['found']);
        $this->assertCount(1, $result['passages'], 'Only 1 active compliance entry should be returned');
        $this->assertStringNotContainsString('STALE', $result['passages'][0]['content'],
            'Inactive KB entries must never be returned');
    }

    public function test_get_compliance_doc_returns_not_found_for_missing_doc_type(): void
    {
        $result = $this->tools->dispatch('get_compliance_doc', [
            'region'   => 'africa',
            'doc_type' => 'faq',  // no FAQ entries seeded
        ], 'trace-007');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('No faq document', $result['message']);
    }

    // =========================================================================
    // Adversarial Test 5: Unknown tool name must throw, not silently pass
    // =========================================================================

    public function test_dispatch_throws_for_unknown_tool_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown tool/');

        $this->tools->dispatch('invent_a_discount', ['amount' => '50%'], 'trace-008');
    }

    // =========================================================================
    // Adversarial Test 6: Quote PDF uses DB price — not any caller-passed price
    // =========================================================================

    public function test_create_quote_pdf_uses_db_price_not_caller_value(): void
    {
        // Even if a bad actor passes a fake price in inputs, it must be ignored
        $result = $this->tools->dispatch('create_quote_pdf', [
            'lead_id'     => $this->lead->id,
            'sku'         => 'SKU-PROC-X',
            'region'      => 'europe',
            'quantity'    => 2,
            'fake_price'  => 1.00,  // attacker-supplied — must be silently ignored
        ], 'trace-009');

        $this->assertTrue($result['success']);
        // Price in result must equal DB price (800), not the fake 1.00
        $this->assertEquals(800.00, $result['price'], 'Quote price must come from DB, not caller input');
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('product:SKU-PROC-X', $result['source']);
    }

    public function test_create_quote_pdf_fails_for_unknown_sku(): void
    {
        $result = $this->tools->dispatch('create_quote_pdf', [
            'lead_id'  => $this->lead->id,
            'sku'      => 'FAKE-SKU',
            'region'   => 'europe',
            'quantity' => 1,
        ], 'trace-010');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('FAKE-SKU', $result['message']);
    }

    // =========================================================================
    // Adversarial Test 7: Product spec — exact DB values returned
    // =========================================================================

    public function test_get_product_spec_returns_exact_db_values(): void
    {
        $result = $this->tools->dispatch('get_product_spec', ['sku' => 'SKU-PROC-X'], 'trace-011');

        $this->assertTrue($result['found']);
        $this->assertEquals('8-core 3.5GHz', $result['spec_processor']);
        $this->assertEquals('16GB', $result['spec_ram']);
        $this->assertEquals('512GB SSD', $result['spec_storage']);
    }

    public function test_get_product_spec_returns_not_found_for_unknown_sku(): void
    {
        $result = $this->tools->dispatch('get_product_spec', ['sku' => 'NO-SUCH-SKU'], 'trace-012');
        $this->assertFalse($result['found']);
        $this->assertArrayNotHasKey('spec_processor', $result);
    }
}
