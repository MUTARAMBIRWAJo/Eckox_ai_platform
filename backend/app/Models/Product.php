<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'price_eur', 'price_usd', 'stock_level',
        'spec_processor', 'spec_ram', 'spec_storage',
        'cost_basis', 'supplier_name', 'margin_percent', 'internal_notes'
    ];

    protected $hidden = [
        'cost_basis',
        'supplier_name',
        'margin_percent',
        'internal_notes',
    ];
}
