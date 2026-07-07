<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent production operation run (Sprint 19). The evidence-backed record
 * of a post-handover operations review: aggregate health signals, incident /
 * backup-restore / support-SLA / maintenance / release-rollback summaries,
 * evidence references, and a GO/WATCH/NO_GO decision. Approve/block append actor
 * metadata; evidence is never deleted. No secrets are stored on the row.
 */
class ProductionOperationRun extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_HEALTHY = 'HEALTHY';
    public const STATUS_WATCH = 'WATCH';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_CLOSED = 'CLOSED';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_HEALTHY,
        self::STATUS_WATCH,
        self::STATUS_BLOCKED,
        self::STATUS_CLOSED,
    ];

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_GO,
        self::DECISION_WATCH,
        self::DECISION_NO_GO,
    ];

    protected $fillable = [
        'operation_reference',
        'status',
        'decision',
        'window_start',
        'window_end',
        'health_signals',
        'incident_summary',
        'backup_restore_summary',
        'support_sla_summary',
        'maintenance_summary',
        'release_rollback_summary',
        'evidence_references',
        'created_by',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'approved_at' => 'datetime',
            'health_signals' => 'array',
            'incident_summary' => 'array',
            'backup_restore_summary' => 'array',
            'support_sla_summary' => 'array',
            'maintenance_summary' => 'array',
            'release_rollback_summary' => 'array',
            'evidence_references' => 'array',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeWithDecision(Builder $query, string $decision): Builder
    {
        return $query->where('decision', $decision);
    }
}
