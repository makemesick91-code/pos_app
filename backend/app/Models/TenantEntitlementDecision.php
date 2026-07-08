<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 32 — a persisted runtime entitlement decision (audit trail).
 *
 * Written only by EntitlementAuditService for denied/degraded/read_only/bypassed
 * decisions (ENT-R018). metadata_json is redacted before persistence, so this
 * model never carries secrets or raw PII. UPDATED_AT is disabled: a decision is
 * an immutable historical fact.
 */
class TenantEntitlementDecision extends Model
{
    public const UPDATED_AT = null;

    public const DECISION_ALLOWED = 'allowed';

    public const DECISION_DENIED = 'denied';

    public const DECISION_DEGRADED = 'degraded';

    public const DECISION_READ_ONLY = 'read_only';

    public const DECISION_BYPASSED = 'bypassed';

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'subject_type',
        'subject_id',
        'entitlement_key',
        'resource_type',
        'action',
        'decision',
        'reason_code',
        'plan_code',
        'current_usage',
        'limit_value',
        'billing_state',
        'subscription_state',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'current_usage' => 'integer',
            'limit_value' => 'integer',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('decision', self::DECISION_DENIED);
    }
}
