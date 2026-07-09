<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 36 — a detected observability anomaly (OBS-R013..R019).
 * Read-only detection output; recording one never mutates any domain state.
 */
class ObservabilityAnomalyEvent extends Model
{
    public const CATEGORY_QUEUE = 'queue';
    public const CATEGORY_SCHEDULER = 'scheduler';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_ENTITLEMENT = 'entitlement';
    public const CATEGORY_ONBOARDING = 'onboarding';
    public const CATEGORY_ANDROID_SYNC = 'android_sync';
    public const CATEGORY_EXPORT_REPORT = 'export_report';
    public const CATEGORY_STORAGE = 'storage';
    public const CATEGORY_CACHE = 'cache';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_OTHER = 'other';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_LINKED_TO_INCIDENT = 'linked_to_incident';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'tenant_id',
        'anomaly_key',
        'category',
        'severity',
        'status',
        'reason_code',
        'related_subject_type',
        'related_subject_id',
        'first_seen_at',
        'last_seen_at',
        'occurrence_count',
        'summary_safe',
        'metadata_json',
        'acknowledged_by_user_id',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'occurrence_count' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_SUGGESTED]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'anomaly_key' => $this->anomaly_key,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'reason_code' => $this->reason_code,
            'occurrence_count' => $this->occurrence_count,
            'first_seen_at' => optional($this->first_seen_at)->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)->toIso8601String(),
            'summary_safe' => $this->summary_safe,
        ];
    }
}
