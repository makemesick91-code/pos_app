<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A persistent pilot defect (Sprint 17). Carries severity, status, a blocking
 * flag, reporter/assignee, functional area, optional tenant/store context, an
 * SLA due timestamp, accepted-risk governance fields, and fix-verification
 * fields. Lifecycle changes append immutable PilotDefectEvent rows; accepted
 * risk never overwrites the original severity. No secrets are stored on the row.
 */
class PilotDefect extends Model
{
    public const SEVERITY_BLOCKER = 'BLOCKER';
    public const SEVERITY_CRITICAL = 'CRITICAL';
    public const SEVERITY_MAJOR = 'MAJOR';
    public const SEVERITY_MINOR = 'MINOR';
    public const SEVERITY_TRIVIAL = 'TRIVIAL';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_FIXED = 'FIXED';
    public const STATUS_RETEST = 'RETEST';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_ACCEPTED_RISK = 'ACCEPTED_RISK';

    public const VERIFICATION_PASS = 'PASS';
    public const VERIFICATION_FAIL = 'FAIL';

    /** @var array<int,string> */
    public const SEVERITIES = [
        self::SEVERITY_BLOCKER,
        self::SEVERITY_CRITICAL,
        self::SEVERITY_MAJOR,
        self::SEVERITY_MINOR,
        self::SEVERITY_TRIVIAL,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FIXED,
        self::STATUS_RETEST,
        self::STATUS_VERIFIED,
        self::STATUS_CLOSED,
        self::STATUS_ACCEPTED_RISK,
    ];

    /** @var array<int,string> */
    public const AREAS = [
        'AUTH',
        'SYNC',
        'CASHIER',
        'PAYMENT_QRIS',
        'RECEIPT_PRINTER',
        'OFFLINE_SYNC',
        'INVENTORY',
        'REPORTING',
        'CLOSING',
        'SUBSCRIPTION_DEVICE',
        'ADMIN_ONBOARDING',
        'ANDROID_APP',
        'BACKEND_API',
        'OTHER',
    ];

    /** Statuses considered "still open" for gating/burn-down purposes. */
    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FIXED,
        self::STATUS_RETEST,
    ];

    protected $fillable = [
        'defect_reference',
        'tenant_id',
        'store_id',
        'reported_by',
        'assigned_to',
        'area',
        'severity',
        'status',
        'blocking',
        'title',
        'description',
        'steps_to_reproduce',
        'expected_result',
        'actual_result',
        'environment',
        'evidence_reference',
        'sla_due_at',
        'sla_breached_at',
        'accepted_risk_at',
        'accepted_risk_by',
        'accepted_risk_reason',
        'accepted_risk_expires_at',
        'fixed_at',
        'verified_at',
        'verified_by',
        'verification_result',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'blocking' => 'boolean',
            'environment' => 'array',
            'metadata' => 'array',
            'sla_due_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'accepted_risk_at' => 'datetime',
            'accepted_risk_expires_at' => 'datetime',
            'fixed_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function acceptedRiskBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_risk_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PilotDefectEvent::class);
    }

    public function scopeWithSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isAcceptedRisk(): bool
    {
        return $this->accepted_risk_at !== null;
    }

    /**
     * Whether the accepted-risk acceptance is still valid (present and not past
     * its expiry/review date).
     */
    public function hasValidAcceptedRisk(): bool
    {
        if ($this->accepted_risk_at === null) {
            return false;
        }

        if ($this->accepted_risk_expires_at === null) {
            return true;
        }

        return $this->accepted_risk_expires_at->isFuture();
    }
}
