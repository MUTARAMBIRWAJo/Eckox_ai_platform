<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = [
        'region', 'doc_type', 'product_category', 'content', 'effective_date', 'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_active'      => 'boolean',
    ];

    /** Scope: only return entries that have not been soft-disabled. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
