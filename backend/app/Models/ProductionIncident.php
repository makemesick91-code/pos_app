<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent production incident (Sprint 19). Carries a severity (P0–P4), a
 * lifecycle status, an impacted area, optional tenant/store context, an SLA due
 * timestamp, an optional SLA breach timestamp, owner/reporter, accepted-risk
 * governance fields, and an evidence reference. Open P0/P1 incidents force NO-GO
 * unless a valid accepted risk exists; open P2 forces WATCH. Accepted risk never
 * hides the original severity. No secrets are stored on the row.
 */
class ProductionIncident extends Model
{
    public const SEVERITY_P0 = 'P0';
    public const SEVERITY_P1 = 'P1';
    public const SEVERITY_P2 = 'P2';
    public const SEVERITY_P3 = 'P3';
    public const SEVERITY_P4 = 'P4';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const STATUS_INVESTIGATING = 'INVESTIGATING';
    public const STATUS_MITIGATED = 'MITIGATED';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_ACCEPTED_RISK = 'ACCEPTED_RISK';

    /** @var array<int,string> */
    public const SEVERITIES = [
        self::SEVERITY_P0,
        self::SEVERITY_P1,
        self::SEVERITY_P2,
        self::SEVERITY_P3,
        self::SEVERITY_P4,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_INVESTIGATING,
        self::STATUS_MITIGATED,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_ACCEPTED_RISK,
    ];

    /** @var array<int,string> */
    public const AREAS = [
        'BACKEND_API',
        'ANDROID_APP',
        'AUTH',
        'TENANT_CONTEXT',
        'PRODUCT_SYNC',
        'CASHIER',
        'PAYMENT_QRIS',
        'OFFLINE_SYNC',
        'RECEIPT_PRINTER',
        'INVENTORY',
        'REPORTING',
        'CLOSING',
        'SUBSCRIPTION_DEVICE',
        'ADMIN_ONBOARDING',
        'DATABASE',
        'BACKUP_RESTORE',
        'DEPLOYMENT',
        'OTHER',
    ];

    /** Statuses considered "still open" for gating/SLA purposes. */
    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_INVESTIGATING,
        self::STATUS_MITIGATED,
    ];

    protected $fillable = [
        'incident_reference',
        'tenant_id',
        'store_id',
        'reported_by',
        'assigned_to',
        'area',
        'severity',
        'status',
        'impact',
        'title',
        'description',
        'detected_at',
        'started_at',
        'resolved_at',
        'closed_at',
        'sla_due_at',
        'sla_breached_at',
        'accepted_risk_at',
        'accepted_risk_by',
        'accepted_risk_reason',
        'accepted_risk_expires_at',
        'resolution_summary',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'accepted_risk_at' => 'datetime',
            'accepted_risk_expires_at' => 'datetime',
            'metadata' => 'array',
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

    public function isSlaBreached(): bool
    {
        return $this->sla_breached_at !== null;
    }
}
