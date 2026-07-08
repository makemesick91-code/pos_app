<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 26 — a single usage limit for a plan. limit_value is the numeric cap
 * (null = not configured); unlimited=true means no cap. period is
 * lifetime/monthly/daily/current. Synced from config/tenant_plan.php by
 * TenantPlanRegistrar.
 */
class PlanUsageLimit extends Model
{
    public const PERIOD_LIFETIME = 'lifetime';
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_DAILY = 'daily';
    public const PERIOD_CURRENT = 'current';

    protected $fillable = [
        'tenant_plan_id',
        'limit_key',
        'limit_value',
        'unlimited',
        'period',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'unlimited' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantPlan::class, 'tenant_plan_id');
    }
}
