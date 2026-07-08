<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 35 — the app-specific support action ledger row (SUP-R006/R024/R026).
 *
 * Append-only: created_at only, no updated_at. `metadata_json` is redacted.
 */
class TenantSupportAction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_READ_CONTEXT_STARTED = 'read_context_started';
    public const TYPE_READ_CONTEXT_ENDED = 'read_context_ended';
    public const TYPE_DEVICE_REVOKED = 'device_revoked';
    public const TYPE_DEVICE_REACTIVATED = 'device_reactivated';
    public const TYPE_INCIDENT_CREATED = 'incident_created';
    public const TYPE_INCIDENT_UPDATED = 'incident_updated';
    public const TYPE_NOTE_ADDED = 'note_added';
    public const TYPE_DIAGNOSTIC_EXPORTED = 'diagnostic_exported';
    public const TYPE_BLOCKED_ACTION_REVIEWED = 'blocked_action_reviewed';
    public const TYPE_SYNC_FAILURE_REVIEWED = 'sync_failure_reviewed';
    public const TYPE_IMPERSONATION_DENIED = 'impersonation_denied';
    public const TYPE_OTHER = 'other';

    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_DENIED = 'denied';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action_key',
        'action_type',
        'status',
        'reason_code',
        'related_subject_type',
        'related_subject_id',
        'support_session_id',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'actor_user_id' => $this->actor_user_id,
            'action_key' => $this->action_key,
            'action_type' => $this->action_type,
            'status' => $this->status,
            'reason_code' => $this->reason_code,
            'support_session_id' => $this->support_session_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
