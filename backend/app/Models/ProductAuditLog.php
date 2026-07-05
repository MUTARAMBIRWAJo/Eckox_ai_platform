<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAuditLog extends Model
{
    protected $fillable = ['product_sku', 'user_name', 'action', 'old_value', 'new_value'];
}
