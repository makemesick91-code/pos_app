<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A production handover package (Sprint 18). Aggregates release readiness,
 * operator/admin handover, support/SLA handover, backup/restore handover, and
 * the release ownership matrix into a sign-off-driven artifact. candidate_commit
 * and candidate_tag are references only. Status changes are conservative and
 * never delete previous evidence. No secret is stored on the row.
 */
class ProductionHandoverPackage extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_READY = 'READY';
    public const STATUS_WATCH = 'WATCH';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_HANDED_OVER = 'HANDED_OVER';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_READY,
        self::STATUS_WATCH,
        self::STATUS_BLOCKED,
        self::STATUS_HANDED_OVER,
    ];

    protected $fillable = [
        'handover_reference',
        'pilot_closure_run_id',
        'status',
        'decision',
        'candidate_commit',
        'candidate_tag',
        'production_readiness_summary',
        'operator_handover_summary',
        'admin_handover_summary',
        'support_sla_summary',
        'backup_restore_summary',
        'ownership_matrix',
        'checklist',
        'evidence_references',
        'created_by',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'production_readiness_summary' => 'array',
            'operator_handover_summary' => 'array',
            'admin_handover_summary' => 'array',
            'support_sla_summary' => 'array',
            'backup_restore_summary' => 'array',
            'ownership_matrix' => 'array',
            'checklist' => 'array',
            'evidence_references' => 'array',
            'metadata' => 'array',
        ];
    }

    public function closureRun(): BelongsTo
    {
        return $this->belongsTo(PilotClosureRun::class, 'pilot_closure_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function signoffs(): HasMany
    {
        return $this->hasMany(ProductionHandoverSignoff::class);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
