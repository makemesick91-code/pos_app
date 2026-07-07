<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A platform admin action audit record (Sprint 11). Records who did what to
 * which target, with sanitized before/after snapshots. Never stores secrets or
 * raw payment gateway payloads. See Sprint 11 evidence.
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const ACTION_TENANT_VIEWED = 'TENANT_VIEWED';
    public const ACTION_SUBSCRIPTION_ASSIGNED = 'SUBSCRIPTION_ASSIGNED';
    public const ACTION_SUBSCRIPTION_UPDATED = 'SUBSCRIPTION_UPDATED';
    public const ACTION_DEVICE_REVOKED = 'DEVICE_REVOKED';
    public const ACTION_PLAN_CREATED = 'PLAN_CREATED';
    public const ACTION_PLAN_UPDATED = 'PLAN_UPDATED';
    public const ACTION_PLAN_DEACTIVATED = 'PLAN_DEACTIVATED';
    public const ACTION_TENANT_ONBOARDED = 'TENANT_ONBOARDED';
    public const ACTION_TENANT_ONBOARDING_REPLAYED = 'TENANT_ONBOARDING_REPLAYED';
    public const ACTION_DEMO_DATA_SEEDED = 'DEMO_DATA_SEEDED';
    public const ACTION_DEMO_DATA_RESET = 'DEMO_DATA_RESET';

    public const TARGET_TENANT = 'tenant';
    public const TARGET_SUBSCRIPTION = 'tenant_subscription';
    public const TARGET_DEVICE = 'registered_device';
    public const TARGET_PLAN = 'subscription_plan';
    public const TARGET_ONBOARDING_RUN = 'tenant_onboarding_run';

    protected $fillable = [
        'actor_user_id',
        'action',
        'target_type',
        'target_id',
        'tenant_id',
        'before_values',
        'after_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForActor(Builder $query, int $actorUserId): Builder
    {
        return $query->where('actor_user_id', $actorUserId);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
