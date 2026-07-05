<?php

namespace Tests\Feature;

use App\Models\AiActionsLog;
use App\Models\AiDecision;
use App\Models\KnowledgeBase;
use App\Models\Lead;
use App\Models\MarketingApproval;
use App\Models\OutboundMessage;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Stub out EmbeddingService so KB tests don't hit OpenAI
        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('embedAndStore')->andReturn(null);
            $mock->shouldReceive('findSimilar')->andReturn([
                [
                    'similarity' => 0.91,
                    'doc_type'   => 'compliance',
                    'content'    => 'CE certified',
                ],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escalation Queue
    // ─────────────────────────────────────────────────────────────────────────

    public function test_escalation_index_returns_escalated_decisions(): void
    {
        $lead = Lead::factory()->create(['name' => 'Test Lead']);

        AiDecision::create([
            'trace_id'      => 'trace-esc-001',
            'lead_id'       => $lead->id,
            'decision_type' => 'escalate',
            'intent'        => 'guardrail_failure',
            'confidence'    => 0.95,
            'response'      => ['reply_text' => 'Escalated to human.'],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/escalations');

        $response->assertOk()
            ->assertJsonFragment(['traceId' => 'trace-esc-001'])
            ->assertJsonFragment(['leadName' => 'Test Lead']);
    }

    public function test_escalation_index_filters_by_reason(): void
    {
        $lead = Lead::factory()->create();

        AiDecision::create([
            'trace_id'      => 'trace-esc-002',
            'lead_id'       => $lead->id,
            'decision_type' => 'escalate',
            'intent'        => 'injection_detected',
            'confidence'    => 0.99,
            'response'      => [],
        ]);

        AiDecision::create([
            'trace_id'      => 'trace-esc-003',
            'lead_id'       => $lead->id,
            'decision_type' => 'escalate',
            'intent'        => 'guardrail_failure',
            'confidence'    => 0.90,
            'response'      => [],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/escalations?reason=injection_detected');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('trace-esc-002', $data[0]['traceId']);
    }

    public function test_escalation_takeover_resolves_escalation(): void
    {
        $lead = Lead::factory()->create(['phone' => '+12125550100']);

        AiDecision::create([
            'trace_id'      => 'trace-esc-takeover',
            'lead_id'       => $lead->id,
            'decision_type' => 'escalate',
            'intent'        => 'guardrail_failure',
            'confidence'    => 0.92,
            'response'      => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/escalations/trace-esc-takeover/takeover', [
                'reply' => 'Hello, a human agent will assist you shortly.',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('ai_decisions', [
            'trace_id' => 'trace-esc-takeover',
            'intent'   => 'manual_override',
        ]);

        $this->assertDatabaseHas('outbound_messages', [
            'trace_id' => 'trace-esc-takeover',
            'content'  => 'Hello, a human agent will assist you shortly.',
        ]);
    }

    public function test_escalation_requires_authentication(): void
    {
        $this->getJson('/api/escalations')->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Trace Viewer
    // ─────────────────────────────────────────────────────────────────────────

    public function test_trace_show_returns_trace_data(): void
    {
        $lead = Lead::factory()->create(['name' => 'Alice Wangari']);

        AiActionsLog::create([
            'trace_id'          => 'trace-show-001',
            'lead_id'           => $lead->id,
            'llm_provider'      => 'openai',
            'node_path'         => ['intent_classification', 'tool_execution', 'guardrail_validation'],
            'latency_ms'        => ['llm_reasoning' => 820],
            'tool_calls'        => [['tool' => 'get_product_info', 'result' => 'ok']],
            'guardrail_verdict' => ['passed' => true, 'errors' => []],
            'decision_type'     => 'reply',
            'action_executed'   => 'send_whatsapp',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/traces/trace-show-001');

        $response->assertOk()
            ->assertJsonFragment(['traceId' => 'trace-show-001'])
            ->assertJsonFragment(['llmProvider' => 'openai'])
            ->assertJsonFragment(['leadName' => 'Alice Wangari'])
            ->assertJsonFragment(['hasFailover' => false])
            ->assertJsonFragment(['hasRetryCycle' => false]);
    }

    public function test_trace_show_returns_404_for_unknown_trace(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/traces/unknown-trace-xyz')
            ->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Knowledge Base CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function test_knowledge_base_index_returns_entries(): void
    {
        KnowledgeBase::create([
            'region'   => 'europe',
            'doc_type' => 'compliance',
            'content'  => 'CE marked — European Conformity',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/knowledge-base')
            ->assertOk()
            ->assertJsonFragment(['doc_type' => 'compliance']);
    }

    public function test_knowledge_base_store_creates_entry(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/knowledge-base', [
            'region'   => 'africa',
            'doc_type' => 'pricing',
            'content'  => 'Eckox Processor X costs $340 in Nigeria.',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['region' => 'africa']);

        $this->assertDatabaseHas('knowledge_base', [
            'doc_type' => 'pricing',
            'region'   => 'africa',
        ]);
    }

    public function test_knowledge_base_store_validates_region(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/knowledge-base', [
                'region'   => 'asia',
                'doc_type' => 'pricing',
                'content'  => 'Some content',
            ])
            ->assertUnprocessable();
    }

    public function test_knowledge_base_update_changes_content(): void
    {
        $kb = KnowledgeBase::create([
            'region'   => 'europe',
            'doc_type' => 'compliance',
            'content'  => 'Old content',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/knowledge-base/{$kb->id}", [
                'content' => 'Updated compliance text.',
            ])
            ->assertOk()
            ->assertJsonFragment(['content' => 'Updated compliance text.']);
    }

    public function test_knowledge_base_destroy_removes_entry(): void
    {
        $kb = KnowledgeBase::create([
            'region'   => 'europe',
            'doc_type' => 'compliance',
            'content'  => 'To be deleted.',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/knowledge-base/{$kb->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('knowledge_base', ['id' => $kb->id]);
    }

    public function test_knowledge_base_test_endpoint_returns_results(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/knowledge-base/test', ['query' => 'CE certification Europe']);

        $response->assertOk()
            ->assertJsonStructure([['score', 'content']]);
    }

    public function test_knowledge_base_test_requires_query(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/knowledge-base/test', [])
            ->assertUnprocessable();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product Catalog + Audit Logs
    // ─────────────────────────────────────────────────────────────────────────

    public function test_products_index_returns_list(): void
    {
        Product::create([
            'name'      => 'Eckox Processor X',
            'sku'       => 'EPX-001',
            'price_eur' => 299.99,
            'price_usd' => 320.00,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'EPX-001']);
    }

    public function test_products_store_creates_product_with_audit_log(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/products', [
            'name'      => 'Eckox Lite',
            'sku'       => 'EL-001',
            'price_eur' => 149.00,
            'price_usd' => 160.00,
        ]);

        $response->assertCreated()->assertJsonFragment(['sku' => 'EL-001']);

        $this->assertDatabaseHas('product_audit_logs', [
            'product_sku' => 'EL-001',
            'action'      => 'Product created',
        ]);
    }

    public function test_products_update_logs_price_change(): void
    {
        $product = Product::create([
            'name'      => 'Eckox Pro',
            'sku'       => 'EP-001',
            'price_eur' => 399.00,
            'price_usd' => 430.00,
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/products/{$product->id}", [
                'price_eur' => 379.00,
            ])
            ->assertOk();

        $this->assertDatabaseHas('product_audit_logs', [
            'product_sku' => 'EP-001',
            'action'      => 'Price updated (EUR)',
        ]);
    }

    public function test_products_destroy_removes_product_with_audit_log(): void
    {
        $product = Product::create([
            'name'      => 'Eckox Budget',
            'sku'       => 'EB-001',
            'price_eur' => 99.00,
            'price_usd' => 105.00,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);

        $this->assertDatabaseHas('product_audit_logs', [
            'product_sku' => 'EB-001',
            'action'      => 'Product deleted',
        ]);
    }

    public function test_products_audit_logs_returns_recent_logs(): void
    {
        // Create via API so that ProductController logs the audit event
        $this->actingAs($this->user)->postJson('/api/products', [
            'name'      => 'Eckox X',
            'sku'       => 'EX-AUDIT',
            'price_eur' => 199.00,
            'price_usd' => 215.00,
        ])->assertCreated();

        $this->actingAs($this->user)
            ->getJson('/api/products/audit-logs')
            ->assertOk()
            ->assertJsonFragment(['product_sku' => 'EX-AUDIT']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard Stats & Provider Health
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_stats_returns_expected_structure(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure(['pipeline', 'avgLatencyMs', 'conversionRate', 'totalDecisions']);
    }

    public function test_dashboard_provider_health_returns_three_providers(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/dashboard/provider-health');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(3, $data);

        $names = array_column($data, 'name');
        $this->assertContains('OpenAI (Primary)', $names);
        $this->assertContains('Anthropic Claude', $names);
        $this->assertContains('Groq LLaMA', $names);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketing Approvals
    // ─────────────────────────────────────────────────────────────────────────

    public function test_marketing_approvals_index_returns_list(): void
    {
        MarketingApproval::create([
            'campaign_name' => 'Back to School Campaign',
            'content'       => 'Get 20% off all Eckox Processors this September!',
            'channel'       => 'linkedin',
            'status'        => 'pending',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/marketing-approvals')
            ->assertOk()
            ->assertJsonFragment(['campaign_name' => 'Back to School Campaign']);
    }

    public function test_marketing_approvals_index_filters_by_status(): void
    {
        MarketingApproval::create([
            'campaign_name' => 'Approved Campaign',
            'content'       => 'Content A',
            'channel'       => 'twitter',
            'status'        => 'approved',
        ]);

        MarketingApproval::create([
            'campaign_name' => 'Pending Campaign',
            'content'       => 'Content B',
            'channel'       => 'facebook',
            'status'        => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/marketing-approvals?status=pending');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Pending Campaign', $data[0]['campaign_name']);
    }

    public function test_marketing_approval_approve_changes_status(): void
    {
        $approval = MarketingApproval::create([
            'campaign_name' => 'Q3 Launch',
            'content'       => 'Eckox Processor X is here!',
            'channel'       => 'linkedin',
            'status'        => 'pending',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/marketing-approvals/{$approval->id}/approve")
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'approved']);

        $this->assertDatabaseHas('marketing_approvals', [
            'id'     => $approval->id,
            'status' => 'approved',
        ]);
    }

    public function test_marketing_approval_reject_changes_status(): void
    {
        $approval = MarketingApproval::create([
            'campaign_name' => 'Risky Campaign',
            'content'       => 'Unverified claims here.',
            'channel'       => 'twitter',
            'status'        => 'pending',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/marketing-approvals/{$approval->id}/reject", [
                'reason' => 'Contains unverified pricing claims.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'rejected']);

        $this->assertDatabaseHas('marketing_approvals', [
            'id'     => $approval->id,
            'status' => 'rejected',
        ]);
    }

    public function test_marketing_approval_cannot_approve_already_rejected(): void
    {
        $approval = MarketingApproval::create([
            'campaign_name' => 'Already Done',
            'content'       => 'Content.',
            'channel'       => 'facebook',
            'status'        => 'rejected',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/marketing-approvals/{$approval->id}/approve")
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => "Approval is already 'rejected' and cannot be changed."]);
    }

    public function test_marketing_approvals_requires_authentication(): void
    {
        $this->getJson('/api/marketing-approvals')->assertUnauthorized();
    }
}
