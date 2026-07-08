<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 25 — a manual tenant suspension governance record.
 *
 * Created/lifted only by a platform admin through TenantSuspensionService. An
 * ACTIVE row is the authoritative signal that a tenant is manually suspended;
 * the tenant lifecycle guard blocks operational access while it is active.
 * Manual suspension has precedence over subscription renewal/dunning automation.
 */
class TenantManualSuspension extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_LIFTED = 'LIFTED';

    protected $fillable = [
        'tenant_id',
        'status',
        'reason',
        'reason_category',
        'effective_at',
        'lifted_at',
        'lift_reason',
        'suspended_by_user_id',
        'lifted_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'lifted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by_user_id');
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
