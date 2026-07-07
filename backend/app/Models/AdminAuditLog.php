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
    public const ACTION_DEFECT_CREATED = 'DEFECT_CREATED';
    public const ACTION_DEFECT_UPDATED = 'DEFECT_UPDATED';
    public const ACTION_DEFECT_ASSIGNED = 'DEFECT_ASSIGNED';
    public const ACTION_DEFECT_STATUS_CHANGED = 'DEFECT_STATUS_CHANGED';
    public const ACTION_DEFECT_ACCEPTED_RISK = 'DEFECT_ACCEPTED_RISK';
    public const ACTION_DEFECT_FIXED = 'DEFECT_FIXED';
    public const ACTION_DEFECT_VERIFIED = 'DEFECT_VERIFIED';
    public const ACTION_CLOSURE_CREATED = 'CLOSURE_CREATED';
    public const ACTION_CLOSURE_APPROVED = 'CLOSURE_APPROVED';
    public const ACTION_CLOSURE_BLOCKED = 'CLOSURE_BLOCKED';
    public const ACTION_HANDOVER_CREATED = 'HANDOVER_CREATED';
    public const ACTION_HANDOVER_UPDATED = 'HANDOVER_UPDATED';
    public const ACTION_HANDOVER_MARKED_READY = 'HANDOVER_MARKED_READY';
    public const ACTION_HANDOVER_HANDED_OVER = 'HANDOVER_HANDED_OVER';
    public const ACTION_HANDOVER_SIGNOFF_ADDED = 'HANDOVER_SIGNOFF_ADDED';

    public const TARGET_TENANT = 'tenant';
    public const TARGET_SUBSCRIPTION = 'tenant_subscription';
    public const TARGET_DEVICE = 'registered_device';
    public const TARGET_PLAN = 'subscription_plan';
    public const TARGET_ONBOARDING_RUN = 'tenant_onboarding_run';
    public const TARGET_PILOT_DEFECT = 'pilot_defect';
    public const TARGET_PILOT_CLOSURE_RUN = 'pilot_closure_run';
    public const TARGET_PRODUCTION_HANDOVER_PACKAGE = 'production_handover_package';
    public const TARGET_PRODUCTION_HANDOVER_SIGNOFF = 'production_handover_signoff';

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
