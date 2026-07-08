<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 26 — a single feature entitlement flag for a plan. enabled decides
 * whether the plan grants the feature keyed by entitlement_key. Synced from
 * config/tenant_plan.php by TenantPlanRegistrar.
 */
class PlanEntitlement extends Model
{
    protected $fillable = [
        'tenant_plan_id',
        'entitlement_key',
        'enabled',
        'limit_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantPlan::class, 'tenant_plan_id');
    }
}
