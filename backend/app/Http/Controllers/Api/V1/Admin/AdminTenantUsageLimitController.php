<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantUsageLimitResource;
use App\Models\Tenant;
use App\Services\TenantPlan\TenantPlanResolver;
use App\Services\TenantPlan\TenantUsageLimitService;

/**
 * Sprint 26 — platform-admin read-only view of a tenant's usage limits with
 * current usage/remaining, always computed server-side from real DB counts
 * (TPE-R003). Non-meterable limits are reported explicitly.
 */
class AdminTenantUsageLimitController extends Controller
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
        private readonly TenantUsageLimitService $usage,
    ) {}

    public function show(Tenant $tenant): TenantUsageLimitResource
    {
        $plan = $this->resolver->resolve($tenant);

        $limits = [];
        foreach (array_keys((array) config('tenant_plan.usage_limits', [])) as $limitKey) {
            $limits[$limitKey] = $this->usage->canUse($tenant, $limitKey, 0)->toArray();
        }

        return new TenantUsageLimitResource([
            'tenant' => $tenant,
            'plan_key' => $plan->planKey,
            'limits' => $limits,
        ]);
    }
}
