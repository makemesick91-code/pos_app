<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A persistent commercial launch run (Sprint 20). The evidence-backed record of
 * a commercial launch readiness review: aggregate package / pricing /
 * sales-enablement / onboarding-capacity / risk / signoff summaries, evidence
 * references, and a GO/WATCH/NO_GO decision. Approve/block append actor metadata;
 * evidence is never deleted. No secrets are stored on the row.
 */
class CommercialLaunchRun extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_READY = 'READY';
    public const STATUS_WATCH = 'WATCH';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_LAUNCHED = 'LAUNCHED';
    public const STATUS_CLOSED = 'CLOSED';

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
        self::STATUS_LAUNCHED,
        self::STATUS_CLOSED,
    ];

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_GO,
        self::DECISION_WATCH,
        self::DECISION_NO_GO,
    ];

    protected $fillable = [
        'launch_reference',
        'status',
        'decision',
        'window_start',
        'window_end',
        'package_summary',
        'pricing_summary',
        'sales_enablement_summary',
        'onboarding_capacity_summary',
        'risk_summary',
        'signoff_summary',
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
            'package_summary' => 'array',
            'pricing_summary' => 'array',
            'sales_enablement_summary' => 'array',
            'onboarding_capacity_summary' => 'array',
            'risk_summary' => 'array',
            'signoff_summary' => 'array',
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

    public function signoffs(): HasMany
    {
        return $this->hasMany(CommercialLaunchSignoff::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(CommercialLaunchRisk::class);
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
