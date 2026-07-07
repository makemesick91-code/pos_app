<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted hypercare issue triage snapshot (Sprint 17). Stores aggregated
 * counts and a decision summary only — no raw private customer data and no
 * secrets. `snapshot_reference` is unique so a replay reuses the existing row.
 */
class HypercareIssueSnapshot extends Model
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    protected $fillable = [
        'snapshot_reference',
        'decision',
        'issue_counts',
        'blocking_issue_count',
        'major_issue_count',
        'summary',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issue_counts' => 'array',
            'blocking_issue_count' => 'integer',
            'major_issue_count' => 'integer',
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
