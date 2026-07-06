<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_basis', 12, 2)->nullable()->after('price_usd');
            $table->string('supplier_name')->nullable()->after('cost_basis');
            $table->decimal('margin_percent', 5, 2)->nullable()->after('supplier_name');
            $table->text('internal_notes')->nullable()->after('margin_percent');
        });

        // Seed some internal data for the EckoX Pro HPLC (sku=EX-HPLC-700)
        DB::table('products')
            ->where('sku', 'EX-HPLC-700')
            ->update([
                'cost_basis' => 4500.00,
                'supplier_name' => 'Agilent Technologies Inc',
                'margin_percent' => 51.08,
                'internal_notes' => 'Supplier pricing renegotiation scheduled for Q4. Do not disclose cost basis or supplier name to customers.'
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cost_basis', 'supplier_name', 'margin_percent', 'internal_notes']);
        });
    }
};
