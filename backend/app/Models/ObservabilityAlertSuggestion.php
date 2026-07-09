<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 36 — a vendor-neutral alert / incident suggestion (OBS-R018/R021).
 * Never mutates tenant state; an accepted suggestion may create a Sprint 35
 * support incident through SupportIncidentService (audited).
 */
class ObservabilityAlertSuggestion extends Model
{
    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_LINKED_TO_INCIDENT = 'linked_to_incident';

    protected $fillable = [
        'tenant_id',
        'anomaly_event_id',
        'suggested_action',
        'severity',
        'status',
        'support_incident_id',
        'reason_code',
        'summary_safe',
        'metadata_json',
        'created_by_user_id',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function anomalyEvent(): BelongsTo
    {
        return $this->belongsTo(ObservabilityAnomalyEvent::class, 'anomaly_event_id');
    }

    public function scopeSuggested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUGGESTED);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'anomaly_event_id' => $this->anomaly_event_id,
            'suggested_action' => $this->suggested_action,
            'severity' => $this->severity,
            'status' => $this->status,
            'support_incident_id' => $this->support_incident_id,
            'reason_code' => $this->reason_code,
            'summary_safe' => $this->summary_safe,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
