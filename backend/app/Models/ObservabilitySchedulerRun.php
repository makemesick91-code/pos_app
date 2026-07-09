<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sprint 36 — a scheduler/command heartbeat row (OBS-R011). failure_reason is
 * redacted; no secrets/PII are stored.
 */
class ObservabilitySchedulerRun extends Model
{
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'command_name',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'exit_code',
        'failure_reason',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];

    public function scopeForCommand(Builder $query, string $command): Builder
    {
        return $query->where('command_name', $command);
    }
}
