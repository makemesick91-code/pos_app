<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreTenantPlanRequest;
use App\Http\Requests\Api\V1\Admin\UpdateTenantPlanRequest;
use App\Http\Resources\Api\V1\Admin\TenantPlanResource;
use App\Models\TenantPlan;
use App\Services\Admin\AdminAuditLogger;
use App\Services\TenantPlan\TenantPlanRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 26 — platform-admin tenant plan catalogue governance (TPE-R006). Plans
 * are the server-side source of truth (TPE-R001). List/create/update; there is no
 * hard delete (deactivate via status). Mutations are audit-logged (TPE-R007).
 */
class AdminTenantPlanController extends Controller
{
    public function __construct(
        private readonly TenantPlanRegistrar $registrar,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->registrar->ensure();

        return TenantPlanResource::collection(
            TenantPlan::query()->with(['entitlements', 'usageLimits'])->orderBy('id')->get(),
        );
    }

    public function store(StoreTenantPlanRequest $request): JsonResponse
    {
        $plan = TenantPlan::query()->create([
            'key' => $request->validated('key'),
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status', TenantPlan::STATUS_ACTIVE),
            'billing_interval' => $request->validated('billing_interval'),
            'metadata' => $this->audit->sanitize($request->validated('metadata')),
        ]);

        $this->audit->log(
            actor: $request->user(),
            action: 'tenant_plan.create',
            targetType: TenantPlan::class,
            targetId: $plan->id,
            after: ['key' => $plan->key, 'status' => $plan->status],
            request: $request,
        );

        return TenantPlanResource::make($plan->load(['entitlements', 'usageLimits']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTenantPlanRequest $request, TenantPlan $plan): JsonResponse
    {
        $before = ['name' => $plan->name, 'status' => $plan->status];

        $plan->fill(array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'billing_interval' => $request->validated('billing_interval'),
        ], fn ($value) => $value !== null));

        if ($request->has('metadata')) {
            $plan->metadata = $this->audit->sanitize($request->validated('metadata'));
        }

        $plan->save();

        $this->audit->log(
            actor: $request->user(),
            action: 'tenant_plan.update',
            targetType: TenantPlan::class,
            targetId: $plan->id,
            before: $before,
            after: ['name' => $plan->name, 'status' => $plan->status],
            request: $request,
        );

        return TenantPlanResource::make($plan->load(['entitlements', 'usageLimits']))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
