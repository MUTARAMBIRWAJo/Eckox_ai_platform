<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base', function (Blueprint $table) {
            $table->string('embedding_model')->nullable()->after('embedding');
        });

        // Backfill existing rows with current active model name
        DB::table('knowledge_base')->update(['embedding_model' => 'bge-small-en-v1.5-padded']);
    }

    public function down(): void
    {
        Schema::table('knowledge_base', function (Blueprint $table) {
            $table->dropColumn('embedding_model');
        });
    }
};
