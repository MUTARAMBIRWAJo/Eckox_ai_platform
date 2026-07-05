<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiActionsLog extends Model
{
    protected $table = 'ai_actions_log';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'trace_id',
        'lead_id',
        'node_path',
        'latency_ms',
        'llm_provider',
        'tool_calls',
        'guardrail_verdict',
        'decision_type',
        'action_executed',
    ];

    protected $casts = [
        'node_path'         => 'array',
        'latency_ms'        => 'array',
        'tool_calls'        => 'array',
        'guardrail_verdict' => 'array',
        'action_executed'   => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiActionsLog $log) {
            if (empty($log->id)) {
                $log->id = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
