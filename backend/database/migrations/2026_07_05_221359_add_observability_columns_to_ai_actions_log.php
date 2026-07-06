<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions_log', function (Blueprint $table) {
            // Provider & model identification
            $table->string('provider', 30)->nullable()->after('llm_provider');
            $table->string('model_name', 80)->nullable()->after('provider');

            // Token usage
            $table->unsignedInteger('tokens_prompt')->default(0)->after('model_name');
            $table->unsignedInteger('tokens_completion')->default(0)->after('tokens_prompt');

            // Cost tracking
            $table->decimal('cost_usd', 10, 8)->default(0)->after('tokens_completion');

            // Retry & fallback tracking
            $table->unsignedTinyInteger('retries')->default(0)->after('cost_usd');
            $table->boolean('fallback_used')->default(false)->after('retries');

            // Total latency scalar (in addition to the per-node JSON)
            $table->unsignedInteger('total_latency_ms')->nullable()->after('fallback_used');

            // Confidence score from ConfidenceScorer
            $table->decimal('confidence_score', 5, 4)->nullable()->after('total_latency_ms');

            // Intent detected by router
            $table->string('intent', 50)->nullable()->after('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions_log', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'model_name',
                'tokens_prompt',
                'tokens_completion',
                'cost_usd',
                'retries',
                'fallback_used',
                'total_latency_ms',
                'confidence_score',
                'intent',
            ]);
        });
    }
};
