<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 26 — a per-tenant feature entitlement override. An ACTIVE row within its
 * effective window enables/disables a single feature for a tenant on top of its
 * plan. Created only by a platform admin through TenantEntitlementOverrideService,
 * audit-logged, with a mandatory sanitized reason. An override NEVER grants
 * access to a suspended/cancelled/archived tenant (TPE-R005).
 */
class TenantEntitlementOverride extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'entitlement_key',
        'enabled',
        'status',
        'reason',
        'reason_category',
        'effective_from',
        'effective_until',
        'actor_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'metadata' => 'array',
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
