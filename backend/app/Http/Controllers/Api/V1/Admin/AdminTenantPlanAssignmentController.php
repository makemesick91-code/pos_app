<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignTenantPlanRequest;
use App\Http\Resources\Api\V1\Admin\TenantPlanAssignmentResource;
use App\Models\Tenant;
use App\Services\TenantPlan\TenantPlanAssignmentService;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 26 — platform-admin tenant → plan assignment governance (TPE-R006).
 * show() returns the tenant's authoritative resolved plan; assign() supersedes
 * the previous active assignment and audit-logs the change (TPE-R007). Assignment
 * never bypasses tenant lifecycle enforcement (TPE-R004/R005).
 */
class AdminTenantPlanAssignmentController extends Controller
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
        private readonly TenantPlanAssignmentService $assignments,
    ) {}

    public function show(Tenant $tenant): TenantPlanAssignmentResource
    {
        return new TenantPlanAssignmentResource([
            'tenant' => $tenant,
            'decision' => $this->resolver->resolve($tenant),
            'assignment' => $tenant->activePlanAssignment(),
        ]);
    }

    public function assign(AssignTenantPlanRequest $request, Tenant $tenant): JsonResponse
    {
        $this->assignments->assign(
            tenant: $tenant,
            actor: $request->user(),
            planKey: (string) $request->validated('plan_key'),
            source: (string) $request->validated('source', 'platform_admin'),
            reason: $request->validated('reason'),
            effectiveFrom: $request->validated('effective_from'),
            effectiveUntil: $request->validated('effective_until'),
            metadata: $request->validated('metadata'),
            request: $request,
        );

        return (new TenantPlanAssignmentResource([
            'tenant' => $tenant->refresh(),
            'decision' => $this->resolver->resolve($tenant),
            'assignment' => $tenant->activePlanAssignment(),
        ]))
            ->response()
            ->setStatusCode(201);
    }
}
