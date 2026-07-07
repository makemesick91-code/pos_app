<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pilot closure run (Sprint 18). Records the final defect review, accepted-risk
 * review, and handover-readiness summary for a pilot, together with a closure
 * checklist, evidence references, and a GO/WATCH/NO_GO decision. Summaries are
 * aggregate only; no secret is stored on the row.
 */
class PilotClosureRun extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_CLOSED = 'CLOSED';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
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
        'closure_reference',
        'status',
        'decision',
        'window_start',
        'window_end',
        'final_defect_summary',
        'accepted_risk_summary',
        'handover_readiness_summary',
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
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'approved_at' => 'datetime',
            'final_defect_summary' => 'array',
            'accepted_risk_summary' => 'array',
            'handover_readiness_summary' => 'array',
            'checklist' => 'array',
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

    public function handoverPackages(): HasMany
    {
        return $this->hasMany(ProductionHandoverPackage::class);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
