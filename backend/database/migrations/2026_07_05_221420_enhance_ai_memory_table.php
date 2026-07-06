<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_memory', function (Blueprint $table) {
            // Add conversation context fields
            $table->string('conversation_id', 36)->nullable()->after('lead_id');
            $table->string('language', 10)->nullable()->after('conversation_id');
            $table->string('country', 5)->nullable()->after('language');
            $table->json('preferences')->nullable()->after('country');
            $table->string('last_intent', 50)->nullable()->after('preferences');
            $table->decimal('confidence', 5, 4)->nullable()->after('last_intent');
            $table->string('provider_used', 30)->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('ai_memory', function (Blueprint $table) {
            $table->dropColumn([
                'conversation_id',
                'language',
                'country',
                'preferences',
                'last_intent',
                'confidence',
                'provider_used',
            ]);
        });
    }
};
