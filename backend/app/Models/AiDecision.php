<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDecision extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'lead_id', 'trace_id', 'intent', 'region',
        'decision_type', 'confidence', 'prompt', 'response',
    ];

    protected $casts = [
        'prompt'     => 'array',
        'response'   => 'array',
        'confidence' => 'double',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
