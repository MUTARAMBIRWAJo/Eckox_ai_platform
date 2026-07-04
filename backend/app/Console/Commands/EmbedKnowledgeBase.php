<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Services\AI\EmbeddingService;
use Illuminate\Console\Command;

class EmbedKnowledgeBase extends Command
{
    protected $signature = 'kb:embed
                            {--region= : Only embed entries for a specific region (africa|europe)}
                            {--force   : Re-embed even entries that already have an embedding}';

    protected $description = 'Backfill OpenAI embeddings for Knowledge Base entries using pgvector';

    public function __construct(private readonly EmbeddingService $embeddings)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = KnowledgeBase::query();

        if ($region = $this->option('region')) {
            $query->where('region', $region);
            $this->info("Filtering to region: {$region}");
        }

        if (! $this->option('force')) {
            // Only process rows without an existing embedding
            // embedding IS NULL check works even with pgvector column
            $query->whereNull('embedding');
        }

        $entries = $query->get();
        $total   = $entries->count();

        if ($total === 0) {
            $this->info('No Knowledge Base entries require embedding. All up to date.');
            return self::SUCCESS;
        }

        $this->info("Embedding {$total} Knowledge Base entries using {$this->embeddings->getModel()}...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $succeeded = 0;
        $failed    = 0;

        foreach ($entries->chunk(10) as $chunk) {
            foreach ($chunk as $kb) {
                try {
                    $this->embeddings->embedAndStore($kb);
                    $succeeded++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed to embed KB #{$kb->id} ({$kb->doc_type}): " . $e->getMessage());
                }
                $bar->advance();
            }
            // Rate limit guard: 500ms between batches of 10
            usleep(500_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Succeeded: {$succeeded} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
