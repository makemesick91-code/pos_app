<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription renewal candidate (Sprint 24). MAY reference a TenantSubscription
 * and a SaaS billing invoice/account as read-only awareness. Creating a candidate
 * NEVER renews a subscription and a stage change NEVER suspends a tenant.
 * READY_FOR_MANUAL_RENEWAL means an admin decision is required, not an automatic
 * renewal.
 */
class SubscriptionRenewalCandidate extends Model
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_DUNNING_PENDING = 'DUNNING_PENDING';
    public const STATUS_DUNNING_IN_PROGRESS = 'DUNNING_IN_PROGRESS';
    public const STATUS_PAYMENT_PENDING = 'PAYMENT_PENDING';
    public const STATUS_READY_FOR_MANUAL_RENEWAL = 'READY_FOR_MANUAL_RENEWAL';
    public const STATUS_MANUALLY_RENEWED = 'MANUALLY_RENEWED';
    public const STATUS_GRACE_REVIEW = 'GRACE_REVIEW';
    public const STATUS_OVERDUE_REVIEW = 'OVERDUE_REVIEW';
    public const STATUS_DO_NOT_RENEW = 'DO_NOT_RENEW';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STAGE_NOT_DUE = 'NOT_DUE';
    public const STAGE_RENEWAL_WINDOW = 'RENEWAL_WINDOW';
    public const STAGE_GRACE_PERIOD = 'GRACE_PERIOD';
    public const STAGE_OVERDUE = 'OVERDUE';
    public const STAGE_MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const STAGE_CLOSED = 'CLOSED';

    public const PRIORITY_LOW = 'LOW';
    public const PRIORITY_NORMAL = 'NORMAL';
    public const PRIORITY_HIGH = 'HIGH';
    public const PRIORITY_URGENT = 'URGENT';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_REVIEW,
        self::STATUS_DUNNING_PENDING,
        self::STATUS_DUNNING_IN_PROGRESS,
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_READY_FOR_MANUAL_RENEWAL,
        self::STATUS_MANUALLY_RENEWED,
        self::STATUS_GRACE_REVIEW,
        self::STATUS_OVERDUE_REVIEW,
        self::STATUS_DO_NOT_RENEW,
        self::STATUS_ARCHIVED,
    ];

    /** @var array<int,string> */
    public const STAGES = [
        self::STAGE_NOT_DUE,
        self::STAGE_RENEWAL_WINDOW,
        self::STAGE_GRACE_PERIOD,
        self::STAGE_OVERDUE,
        self::STAGE_MANUAL_REVIEW,
        self::STAGE_CLOSED,
    ];

    /** @var array<int,string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'candidate_reference',
        'run_id',
        'tenant_id',
        'tenant_subscription_id',
        'policy_id',
        'status',
        'renewal_stage',
        'current_subscription_status',
        'current_period_start',
        'current_period_end',
        'days_until_expiry',
        'grace_ends_at',
        'billing_invoice_id',
        'billing_account_id',
        'last_payment_evidence_status',
        'priority',
        'assigned_to_user_id',
        'qualified_for_manual_renewal_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'days_until_expiry' => 'integer',
            'grace_ends_at' => 'datetime',
            'qualified_for_manual_renewal_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalRun::class, 'run_id');
    }

    public function tenantSubscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function dunningNotices(): HasMany
    {
        return $this->hasMany(SubscriptionDunningNotice::class, 'candidate_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(SubscriptionRenewalDecision::class, 'candidate_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
