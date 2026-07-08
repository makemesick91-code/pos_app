<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 26 — a tenant → plan assignment. An ACTIVE row within its effective
 * window is the authoritative plan for a tenant (TPE-R001). Created only by a
 * platform admin through TenantPlanAssignmentService and audit-logged; it never
 * bypasses tenant lifecycle enforcement.
 */
class TenantPlanAssignment extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_PLATFORM_ADMIN = 'platform_admin';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_TEST = 'test';

    protected $fillable = [
        'tenant_id',
        'tenant_plan_id',
        'status',
        'effective_from',
        'effective_until',
        'source',
        'assigned_by_user_id',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantPlan::class, 'tenant_plan_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
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
