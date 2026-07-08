<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription renewal risk (Sprint 24). Open CRITICAL/HIGH without a valid
 * accepted risk forces NO-GO; open MEDIUM forces WATCH. No secrets or private
 * customer data stored.
 */
class SubscriptionRenewalRisk extends Model
{
    public const SEVERITY_CRITICAL = 'CRITICAL';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_INFO = 'INFO';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_MITIGATED = 'MITIGATED';
    public const STATUS_ACCEPTED_RISK = 'ACCEPTED_RISK';
    public const STATUS_CLOSED = 'CLOSED';

    public const AREA_PAYMENT_DELAY = 'PAYMENT_DELAY';
    public const AREA_GRACE_PERIOD = 'GRACE_PERIOD';
    public const AREA_RENEWAL_APPROVAL = 'RENEWAL_APPROVAL';
    public const AREA_CUSTOMER_CHURN = 'CUSTOMER_CHURN';
    public const AREA_BILLING_MISMATCH = 'BILLING_MISMATCH';
    public const AREA_PLAN_MISMATCH = 'PLAN_MISMATCH';
    public const AREA_DEVICE_LIMIT_IMPACT = 'DEVICE_LIMIT_IMPACT';
    public const AREA_LEGAL_PRIVACY = 'LEGAL_PRIVACY';
    public const AREA_DUNNING_SLA = 'DUNNING_SLA';
    public const AREA_OPERATIONS = 'OPERATIONS';
    public const AREA_OTHER = 'OTHER';

    /** @var array<int,string> */
    public const SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_HIGH,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_LOW,
        self::SEVERITY_INFO,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_MITIGATED,
        self::STATUS_ACCEPTED_RISK,
        self::STATUS_CLOSED,
    ];

    /** @var array<int,string> */
    public const AREAS = [
        self::AREA_PAYMENT_DELAY,
        self::AREA_GRACE_PERIOD,
        self::AREA_RENEWAL_APPROVAL,
        self::AREA_CUSTOMER_CHURN,
        self::AREA_BILLING_MISMATCH,
        self::AREA_PLAN_MISMATCH,
        self::AREA_DEVICE_LIMIT_IMPACT,
        self::AREA_LEGAL_PRIVACY,
        self::AREA_DUNNING_SLA,
        self::AREA_OPERATIONS,
        self::AREA_OTHER,
    ];

    protected $fillable = [
        'risk_reference',
        'candidate_id',
        'tenant_id',
        'tenant_subscription_id',
        'area',
        'severity',
        'status',
        'title',
        'description',
        'owner_user_id',
        'mitigation',
        'accepted_risk_at',
        'accepted_risk_by',
        'accepted_risk_reason',
        'accepted_risk_expires_at',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accepted_risk_at' => 'datetime',
            'accepted_risk_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalCandidate::class, 'candidate_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
