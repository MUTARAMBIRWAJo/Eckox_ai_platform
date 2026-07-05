<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ai_memory table for long-term behavior summaries, products discussed, etc.
        Schema::create('ai_memory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->string('memory_type'); // e.g. 'summary', 'behavioral_note', 'products_discussed'
            $table->text('content'); // redacted PII content
            $table->timestamps();

            $table->index(['lead_id', 'memory_type']);
        });

        // 2. conversation_states table for short-term orchestrator state persistence
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->string('trace_id')->primary();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->json('state_data'); // Persistent AgentState data structure
            $table->timestamps();
        });

        // 3. ai_actions_log table for graph execution observability and metrics
        Schema::create('ai_actions_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('trace_id')->index();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->json('node_path'); // array of nodes executed, e.g. ["intent", "memory", ...]
            $table->json('latency_ms'); // map of node -> latency_ms
            $table->string('llm_provider')->nullable(); // openai, anthropic, groq
            $table->json('tool_calls')->nullable(); // array of executed tool names + params + returns
            $table->json('guardrail_verdict')->nullable(); // guardrail check result / errors
            $table->string('decision_type')->nullable(); // reply, generate_quote, generate_invoice, escalate
            $table->json('action_executed')->nullable(); // actions taken (e.g. email, whatsapp sent)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions_log');
        Schema::dropIfExists('conversation_states');
        Schema::dropIfExists('ai_memory');
    }
};
