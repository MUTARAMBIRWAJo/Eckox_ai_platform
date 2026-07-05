<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'lead_id', 'type', 'file_url',
        'currency', 'region', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            if (empty($document->id)) {
                $document->id = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
