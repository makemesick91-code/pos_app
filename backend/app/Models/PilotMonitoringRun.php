<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted pilot monitoring run snapshot (Sprint 17). Written only when a
 * monitoring evaluation is explicitly persisted; the daily monitoring command
 * stays read-only. Stores a decision summary — never secrets or raw customer
 * data. `run_reference` is unique so a replay reuses the existing snapshot.
 */
class PilotMonitoringRun extends Model
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    protected $fillable = [
        'run_reference',
        'status',
        'decision',
        'window_start',
        'window_end',
        'signals',
        'summary',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'signals' => 'array',
            'summary' => 'array',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWithDecision(Builder $query, string $decision): Builder
    {
        return $query->where('decision', $decision);
    }
}
