<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upgrade Leads table
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_channel', 50)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('ai_score', 20)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('language', 10)->nullable();
        });

        // 2. Create inbound_messages
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lead_id')->nullable();
            $table->string('channel', 50); // 'whatsapp', 'email'
            $table->text('sender');
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->text('country')->nullable();
            $table->text('language')->nullable();
            $table->timestamps();

            $table->index(['sender', 'channel']);
        });

        // 3. Create ai_decisions
        Schema::create('ai_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lead_id')->nullable();
            $table->uuid('trace_id')->index();
            $table->string('intent', 100)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('decision_type', 50); // 'reply', 'generate_quote', 'generate_invoice', 'escalate', 'ask_clarification'
            $table->double('confidence');
            $table->jsonb('prompt')->nullable();
            $table->jsonb('response')->nullable();
            $table->timestamps();
        });

        // 4. Create outbound_messages
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lead_id')->nullable();
            $table->string('channel', 50);
            $table->text('recipient');
            $table->text('content');
            $table->string('status', 50)->default('queued'); // 'queued', 'sent', 'failed'
            $table->uuid('trace_id')->nullable();
            $table->timestamps();
        });

        // 5. Create documents
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lead_id')->nullable();
            $table->string('type', 50); // 'quote', 'invoice', 'certificate'
            $table->text('file_url');
            $table->string('currency', 10)->nullable();
            $table->string('region', 50)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('ai_decisions');
        Schema::dropIfExists('inbound_messages');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['source_channel', 'last_message_at', 'ai_score', 'region', 'language']);
        });
    }
};
