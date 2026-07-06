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
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('sku')->unique();
                $table->decimal('price_eur', 12, 2);
                $table->decimal('price_usd', 12, 2);
                $table->integer('stock_level')->default(0);
                $table->string('spec_processor')->nullable();
                $table->string('spec_ram')->nullable();
                $table->string('spec_storage')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('knowledge_base')) {
            Schema::create('knowledge_base', function (Blueprint $table) {
                $table->id();
                $table->string('region', 20);           // 'africa', 'europe'
                $table->string('doc_type', 50);         // 'compliance', 'sla', 'faq', 'brochure'
                $table->string('product_category', 100)->nullable();
                $table->text('content');
                $table->date('effective_date')->nullable();  // SRS: metadata tag for KB entries
                $table->boolean('is_active')->default(true); // soft-disable stale entries
                $table->timestamps();

                $table->index(['region', 'doc_type']);
                $table->index(['region', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
        Schema::dropIfExists('products');
    }
};
