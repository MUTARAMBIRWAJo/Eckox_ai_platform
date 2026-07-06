<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Services\AI\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        DB::table('knowledge_base')
            ->where('id', $this->docEuropeActive->id)
            ->update(['embedding' => '[' . implode(',', $vectorEuropeActive) . ']']);

        DB::table('knowledge_base')
            ->where('id', $this->docEuropeInactive->id)
            ->update(['embedding' => '[' . implode(',', $vectorEuropeInactive) . ']']);

        DB::table('knowledge_base')
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

        $realSvc = new EmbeddingService(app(\App\Services\AI\RAG\EmbeddingCache::class));

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

    /**
     * Postgres-backed integration test: Exercises the actual pgvector extension
     * operators and distance measurements to guarantee compatibility with production.
     */
    public function test_pgvector_cosine_similarity_in_postgres(): void
    {
        try {
            DB::connection('pgsql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Postgres connection not available to run pgvector tests.');
            return;
        }

        try {
            DB::connection('pgsql')->transaction(function () {
                $vectorQuery = array_merge([1.0], array_fill(0, 1535, 0.0));
                $vectorDoc   = array_merge([0.9], array_fill(0, 1535, 0.0));

                // Insert dynamic temporary test data
                $id = DB::connection('pgsql')->table('knowledge_base')->insertGetId([
                    'region'           => 'europe',
                    'doc_type'         => 'compliance',
                    'content'          => 'PGVECTOR POSTGRES TEST DOCUMENT.',
                    'is_active'        => true,
                    'embedding'        => '[' . implode(',', $vectorDoc) . ']',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                // Run direct pgvector cosine similarity match query
                $vectorQueryLiteral = '[' . implode(',', $vectorQuery) . ']';
                $results = DB::connection('pgsql')->select(
                    'SELECT id, 1 - (embedding <=> ?) AS similarity
                     FROM knowledge_base
                     WHERE id = ?',
                    [$vectorQueryLiteral, $id]
                );

                $this->assertCount(1, $results);
                $this->assertGreaterThan(0.8, (float) $results[0]->similarity);

                // Throw exception to trigger database rollback
                throw new \RuntimeException('Rollback database for test safety');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'Rollback database for test safety') {
                throw $e;
            }
        }
    }
}
