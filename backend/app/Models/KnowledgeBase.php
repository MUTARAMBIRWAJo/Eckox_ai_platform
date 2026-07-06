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

    protected static function booted(): void
    {
        static::saving(function (KnowledgeBase $kb) {
            // Automatically generate and attach embedding when content is dirty/created.
            // Exclude testing environment unless EmbeddingService is explicitly mocked or configured.
            if ($kb->isDirty('content') && !empty($kb->content)) {
                if (app()->environment() === 'testing' && !app()->bound(EmbeddingService::class)) {
                    return;
                }
                try {
                    $embedSvc = app(EmbeddingService::class);
                    $vector = $embedSvc->embed($kb->content);
                    $kb->embedding = '[' . implode(',', $vector) . ']';
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::channel('production')->error('Auto-embedding failed during KnowledgeBase save', [
                        'doc_type' => $kb->doc_type,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /** Scope: only return entries that have not been soft-disabled. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
