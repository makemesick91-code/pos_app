<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 26 — a subscription plan in the tenant plan catalogue (the server-side
 * source of truth for what a tenant may do). Plans are synced from
 * config/tenant_plan.php by TenantPlanRegistrar and carry per-plan feature
 * entitlements and usage limits. No secrets are stored in metadata.
 */
class TenantPlan extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'key',
        'name',
        'description',
        'status',
        'billing_interval',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    public function usageLimits(): HasMany
    {
        return $this->hasMany(PlanUsageLimit::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TenantPlanAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
