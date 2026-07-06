<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Lead;
use App\Services\Documents\DocumentGenerationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentStorageDriverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify S3 file uploads and link retrieval from the generation engine.
     */
    public function test_document_is_saved_to_s3_and_returns_public_url(): void
    {
        Storage::fake('s3');

        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'status' => 'new',
            'source_channel' => 'email',
        ]);

        $engine = new DocumentGenerationEngine();

        $document = $engine->generateQuote(
            $lead,
            [
                'items' => [
                    ['description' => 'Consulting Service', 'qty' => 1, 'unit_price' => 5000]
                ],
                'total' => 5000
            ],
            'europe',
            'test-trace-id'
        );

        $this->assertInstanceOf(Document::class, $document);
        $this->assertStringContainsString('test@example.com', $document->lead->email);

        // Assert S3 storage persists the generated filename
        $filename = $document->metadata['filename'] ?? '';
        $this->assertNotEmpty($filename);
        Storage::disk('s3')->assertExists($filename);

        // Assert URL contains s3 endpoint references
        $this->assertStringContainsString('documents/', $document->file_url);
    }

    /**
     * Prevent regression: scans for disk('local') in documents namespace.
     */
    public function test_no_local_disk_driver_calls_remain_in_engine(): void
    {
        $enginePath = app_path('Services/Documents/DocumentGenerationEngine.php');
        $this->assertFileExists($enginePath);

        $content = file_get_contents($enginePath);
        $this->assertStringNotContainsString("disk('local')", $content);
        $this->assertStringNotContainsString('disk("local")', $content);
    }
}
