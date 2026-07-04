<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Services\AI\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;
use Tests\TestCase;

class KnowledgeBaseSemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeBase $docEuropeActive;
    private KnowledgeBase $docEuropeInactive;
    private KnowledgeBase $docAfricaActive;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeding database entries
        // 1. Europe Active doc (CE compliance)
        $this->docEuropeActive = KnowledgeBase::create([
            'region'           => 'europe',
            'doc_type'         => 'compliance',
            'product_category' => 'hardware',
            'content'          => 'Eckox Processor X complies with CE marking, ISO 17025, and GDPR.',
            'is_active'        => true,
        ]);

        // 2. Europe Inactive doc (Stale CE compliance)
        $this->docEuropeInactive = KnowledgeBase::create([
            'region'           => 'europe',
            'doc_type'         => 'compliance',
            'product_category' => 'hardware',
            'content'          => 'OLD OBSOLETE COMPLIANCE DOCUMENT.',
            'is_active'        => false,
        ]);

        // 3. Africa Active doc (Delivery SLA)
        $this->docAfricaActive = KnowledgeBase::create([
            'region'           => 'africa',
            'doc_type'         => 'sla',
            'product_category' => 'hardware',
            'content'          => 'Hardware delivery SLA inside Africa is 15 business days.',
            'is_active'        => true,
        ]);
    }

    /**
     * Test semantic match retrieves different wording, excluding cross-region and inactive records.
     */
    public function test_semantic_search_retrieves_relevant_records_and_respects_filters(): void
    {
        // Vectors setup (1536 dimensions)
        $vectorQuery  = array_merge([1.0], array_fill(0, 1535, 0.0));
        $vectorEuropeActive = array_merge([0.9], array_fill(0, 1535, 0.0)); // close to query
        $vectorEuropeInactive = array_merge([0.95], array_fill(0, 1535, 0.0)); // closer to query but inactive
        $vectorAfricaActive = array_merge([0.99], array_fill(0, 1535, 0.0)); // closest to query but wrong region

        // Set the database embeddings directly (bypassing model booted observer saving limits)
        \Illuminate\Support\Facades\DB::table('knowledge_base')
            ->where('id', $this->docEuropeActive->id)
            ->update(['embedding' => '[' . implode(',', $vectorEuropeActive) . ']']);

        \Illuminate\Support\Facades\DB::table('knowledge_base')
            ->where('id', $this->docEuropeInactive->id)
            ->update(['embedding' => '[' . implode(',', $vectorEuropeInactive) . ']']);

        \Illuminate\Support\Facades\DB::table('knowledge_base')
            ->where('id', $this->docAfricaActive->id)
            ->update(['embedding' => '[' . implode(',', $vectorAfricaActive) . ']']);

        // Mock the OpenAI embeddings resource to return our query vector
        OpenAI::fake([
            CreateResponse::fake([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'index' => 0,
                        'embedding' => $vectorQuery,
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ]
            ])
        ]);

        $realSvc = new EmbeddingService();

        // 1. Query Europe region
        $resultsEurope = $realSvc->findSimilar('Give me the certifications', 'europe', 5);

        // Assertions for Europe:
        // - docEuropeActive MUST be returned.
        // - docEuropeInactive MUST NOT be returned (is_active=false).
        // - docAfricaActive MUST NOT be returned (wrong region).
        $this->assertCount(1, $resultsEurope);
        $this->assertEquals($this->docEuropeActive->id, $resultsEurope[0]['id']);
        $this->assertEquals('compliance', $resultsEurope[0]['doc_type']);
    }
}
