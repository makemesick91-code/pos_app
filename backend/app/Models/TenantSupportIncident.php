<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 35 — a tenant support incident (SUP-R003/R023/R024).
 *
 * Tenant-isolated and PII/secret-free: `title_safe`, `summary_safe` and
 * `metadata_json` are redacted before persistence by SupportIncidentService.
 */
class TenantSupportIncident extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_WAITING_TENANT = 'waiting_tenant';
    public const STATUS_WAITING_INTERNAL = 'waiting_internal';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /** Statuses that close out the incident timeline. */
    public const TERMINAL_STATUSES = [self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_CANCELLED];

    protected $fillable = [
        'tenant_id',
        'opened_by_user_id',
        'assigned_to_user_id',
        'incident_number',
        'category',
        'severity',
        'status',
        'title_safe',
        'summary_safe',
        'primary_reason_code',
        'related_subject_type',
        'related_subject_id',
        'opened_at',
        'resolved_at',
        'closed_at',
        'metadata_json',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TenantSupportIncidentNote::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'incident_number' => $this->incident_number,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'title' => $this->title_safe,
            'summary' => $this->summary_safe,
            'primary_reason_code' => $this->primary_reason_code,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'opened_at' => optional($this->opened_at)->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
        ];
    }
}
