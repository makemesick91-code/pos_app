<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant-owned subscription (Sprint 10). The persisted `status` is a hint; the
 * authoritative allowed/blocked decision is always recomputed by
 * SubscriptionStatusService from the date columns so an expired trial or a
 * lapsed active window blocks even if the row still reads ACTIVE. See Sprint 10
 * evidence.
 */
class TenantSubscription extends Model
{
    use HasFactory;

    public const STATUS_TRIAL = 'TRIAL';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_GRACE = 'GRACE';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
        'suspended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
