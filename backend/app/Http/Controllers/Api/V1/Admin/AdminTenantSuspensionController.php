<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LiftTenantSuspensionRequest;
use App\Http\Requests\Api\V1\Admin\SuspendTenantRequest;
use App\Http\Resources\Api\V1\Admin\TenantLifecycleResource;
use App\Models\Tenant;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 25 — platform-admin manual tenant suspension / lift (TLS-R002).
 *
 * Both mutations are idempotent and audit-logged (TLS-R005) and are the only way
 * to create or clear a manual suspension. Renewal/dunning automation can never
 * reach here (TLS-R004). Responses carry the recomputed lifecycle decision.
 */
class AdminTenantSuspensionController extends Controller
{
    public function __construct(
        private readonly TenantSuspensionService $suspensions,
    ) {}

    public function suspend(SuspendTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $result = $this->suspensions->suspend(
            tenant: $tenant,
            actor: $request->user(),
            reason: (string) $request->validated('reason'),
            reasonCategory: $request->validated('reason_category'),
            metadata: $request->validated('metadata'),
            request: $request,
        );

        return (new TenantLifecycleResource([
            'tenant' => $tenant->refresh(),
            'decision' => $result['decision'],
            'active_suspension' => $tenant->activeManualSuspension(),
            'events' => $tenant->lifecycleEvents()->orderByDesc('id')->limit(20)->get(),
        ]))
            ->additional(['already_suspended' => $result['already']])
            ->response()
            ->setStatusCode($result['already'] ? 200 : 201);
    }

    public function lift(LiftTenantSuspensionRequest $request, Tenant $tenant): JsonResponse
    {
        $result = $this->suspensions->lift(
            tenant: $tenant,
            actor: $request->user(),
            reason: (string) $request->validated('reason'),
            metadata: $request->validated('metadata'),
            request: $request,
        );

        return (new TenantLifecycleResource([
            'tenant' => $tenant->refresh(),
            'decision' => $result['decision'],
            'active_suspension' => $tenant->activeManualSuspension(),
            'events' => $tenant->lifecycleEvents()->orderByDesc('id')->limit(20)->get(),
        ]))
            ->additional(['not_suspended' => $result['already']])
            ->response()
            ->setStatusCode(200);
    }
}
