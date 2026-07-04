<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enable pgvector extension and add embedding column + HNSW index.
     * Dimensions: 1536 = OpenAI text-embedding-3-small output size.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Enable pgvector — pre-installed on all Supabase projects
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

            // Add embedding column (nullable — backfilled via kb:embed artisan command)
            DB::statement('ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS embedding vector(1536)');

            // HNSW index — works on small tables unlike ivfflat which needs row count first
            DB::statement('CREATE INDEX IF NOT EXISTS knowledge_base_embedding_idx ON knowledge_base USING hnsw (embedding vector_cosine_ops)');
        } else {
            // SQLite or other driver (e.g. for testing)
            Schema::table('knowledge_base', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS knowledge_base_embedding_idx');
            DB::statement('ALTER TABLE knowledge_base DROP COLUMN IF EXISTS embedding');
        } else {
            Schema::table('knowledge_base', function (Blueprint $table) {
                $table->dropColumn('embedding');
            });
        }
    }
};
