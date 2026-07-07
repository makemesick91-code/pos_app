<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent production maintenance window (Sprint 19). Carries a lifecycle
 * status, a risk level (LOW/MEDIUM/HIGH/CRITICAL), scheduled/actual start-end
 * timestamps, an owner, an optional rollback plan reference, and an evidence
 * reference. A HIGH/CRITICAL window without a rollback plan reference forces
 * WATCH/NO-GO. A maintenance window record never performs a deployment and never
 * stores credentials.
 */
class ProductionMaintenanceWindow extends Model
{
    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_CRITICAL = 'CRITICAL';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_APPROVED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_BLOCKED,
    ];

    /** @var array<int,string> */
    public const RISK_LEVELS = [
        self::RISK_LOW,
        self::RISK_MEDIUM,
        self::RISK_HIGH,
        self::RISK_CRITICAL,
    ];

    /** Statuses considered "still active/pending" for governance purposes. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_APPROVED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'maintenance_reference',
        'status',
        'title',
        'description',
        'scheduled_start_at',
        'scheduled_end_at',
        'actual_start_at',
        'actual_end_at',
        'risk_level',
        'owner_user_id',
        'rollback_plan_reference',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, [self::RISK_HIGH, self::RISK_CRITICAL], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function hasRollbackPlan(): bool
    {
        return $this->rollback_plan_reference !== null && $this->rollback_plan_reference !== '';
    }
}
