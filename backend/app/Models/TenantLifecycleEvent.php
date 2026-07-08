<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 25 — an append-only tenant lifecycle event.
 *
 * Records every governance transition (manual_suspend, manual_lift,
 * lifecycle_transition) with previous/new lifecycle status and a sanitized
 * reason. Written by TenantSuspensionService. Never stores secrets.
 */
class TenantLifecycleEvent extends Model
{
    public const ACTION_MANUAL_SUSPEND = 'manual_suspend';
    public const ACTION_MANUAL_LIFT = 'manual_lift';
    public const ACTION_LIFECYCLE_TRANSITION = 'lifecycle_transition';

    protected $fillable = [
        'tenant_id',
        'action',
        'previous_status',
        'new_status',
        'reason',
        'reason_category',
        'effective_at',
        'actor_user_id',
        'manual_suspension_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
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

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }
}
