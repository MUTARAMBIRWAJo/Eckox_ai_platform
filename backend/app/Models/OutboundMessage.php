<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OutboundMessage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'lead_id', 'channel', 'recipient',
        'content', 'status', 'trace_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutboundMessage $message) {
            if (empty($message->id)) {
                $message->id = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
