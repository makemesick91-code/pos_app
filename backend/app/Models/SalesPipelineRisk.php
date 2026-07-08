<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sales pipeline risk (Sprint 22). Open CRITICAL/HIGH without a valid accepted
 * risk forces NO-GO; open MEDIUM forces WATCH. No secrets or private customer
 * data are stored.
 */
class SalesPipelineRisk extends Model
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

    public const AREA_LEAD_QUALITY = 'LEAD_QUALITY';
    public const AREA_PRICING_EXPECTATION = 'PRICING_EXPECTATION';
    public const AREA_PACKAGE_MISALIGNMENT = 'PACKAGE_MISALIGNMENT';
    public const AREA_ONBOARDING_CAPACITY = 'ONBOARDING_CAPACITY';
    public const AREA_LEGAL_PRIVACY = 'LEGAL_PRIVACY';
    public const AREA_PAYMENT_BILLING_EXPECTATION = 'PAYMENT_BILLING_EXPECTATION';
    public const AREA_DATA_QUALITY = 'DATA_QUALITY';
    public const AREA_FOLLOW_UP_SLA = 'FOLLOW_UP_SLA';
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
        self::AREA_LEAD_QUALITY,
        self::AREA_PRICING_EXPECTATION,
        self::AREA_PACKAGE_MISALIGNMENT,
        self::AREA_ONBOARDING_CAPACITY,
        self::AREA_LEGAL_PRIVACY,
        self::AREA_PAYMENT_BILLING_EXPECTATION,
        self::AREA_DATA_QUALITY,
        self::AREA_FOLLOW_UP_SLA,
        self::AREA_OPERATIONS,
        self::AREA_OTHER,
    ];

    protected $fillable = [
        'risk_reference',
        'sales_lead_id',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function acceptedRiskBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_risk_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
