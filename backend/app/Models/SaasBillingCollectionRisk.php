<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS billing collection risk (Sprint 23). Open CRITICAL/HIGH without a valid
 * accepted risk forces NO-GO; open MEDIUM forces WATCH. No secrets or private
 * customer data stored.
 */
class SaasBillingCollectionRisk extends Model
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
    public const AREA_DISPUTE = 'DISPUTE';
    public const AREA_INVOICE_ACCURACY = 'INVOICE_ACCURACY';
    public const AREA_COLLECTION_SLA = 'COLLECTION_SLA';
    public const AREA_PACKAGE_ALIGNMENT = 'PACKAGE_ALIGNMENT';
    public const AREA_SUBSCRIPTION_STATUS = 'SUBSCRIPTION_STATUS';
    public const AREA_LEGAL_PRIVACY = 'LEGAL_PRIVACY';
    public const AREA_ACCOUNTING_EXPORT = 'ACCOUNTING_EXPORT';
    public const AREA_MANUAL_EVIDENCE_QUALITY = 'MANUAL_EVIDENCE_QUALITY';
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
        self::AREA_DISPUTE,
        self::AREA_INVOICE_ACCURACY,
        self::AREA_COLLECTION_SLA,
        self::AREA_PACKAGE_ALIGNMENT,
        self::AREA_SUBSCRIPTION_STATUS,
        self::AREA_LEGAL_PRIVACY,
        self::AREA_ACCOUNTING_EXPORT,
        self::AREA_MANUAL_EVIDENCE_QUALITY,
        self::AREA_OTHER,
    ];

    protected $fillable = [
        'risk_reference',
        'billing_account_id',
        'invoice_id',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(SaasBillingAccount::class, 'billing_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasBillingInvoice::class, 'invoice_id');
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
