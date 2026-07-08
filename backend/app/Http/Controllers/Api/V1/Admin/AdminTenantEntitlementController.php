<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreTenantEntitlementOverrideRequest;
use App\Http\Resources\Api\V1\Admin\TenantEntitlementResource;
use App\Models\Tenant;
use App\Services\TenantPlan\FeatureEntitlementService;
use App\Services\TenantPlan\TenantEntitlementOverrideService;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 26 — platform-admin tenant feature entitlement governance. show() returns
 * the tenant's effective entitlement map (plan grants with active overrides
 * applied), always computed server-side (TPE-R002). storeOverride() creates a
 * limited, reason-mandatory, audit-logged override (TPE-R006/R007). An override
 * never re-enables a suspended/cancelled/archived tenant (TPE-R005) because the
 * tenant lifecycle guard runs first (TPE-R004).
 */
class AdminTenantEntitlementController extends Controller
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
        private readonly FeatureEntitlementService $entitlements,
        private readonly TenantEntitlementOverrideService $overrides,
    ) {}

    public function show(Tenant $tenant): TenantEntitlementResource
    {
        return new TenantEntitlementResource([
            'tenant' => $tenant,
            'plan_key' => $this->resolver->resolve($tenant)->planKey,
            'entitlements' => $this->entitlements->effectiveMap($tenant),
            'overrides' => $tenant->activeEntitlementOverrides(),
        ]);
    }

    public function storeOverride(StoreTenantEntitlementOverrideRequest $request, Tenant $tenant): JsonResponse
    {
        $this->overrides->set(
            tenant: $tenant,
            actor: $request->user(),
            entitlementKey: (string) $request->validated('entitlement_key'),
            enabled: (bool) $request->validated('enabled'),
            reason: (string) $request->validated('reason'),
            reasonCategory: $request->validated('reason_category'),
            effectiveUntil: $request->validated('effective_until'),
            metadata: $request->validated('metadata'),
            request: $request,
        );

        return (new TenantEntitlementResource([
            'tenant' => $tenant->refresh(),
            'plan_key' => $this->resolver->resolve($tenant)->planKey,
            'entitlements' => $this->entitlements->effectiveMap($tenant),
            'overrides' => $tenant->activeEntitlementOverrides(),
        ]))
            ->response()
            ->setStatusCode(201);
    }
}
