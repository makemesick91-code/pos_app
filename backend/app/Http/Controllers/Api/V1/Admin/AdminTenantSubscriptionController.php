<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminTenantSubscriptionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminTenantSubscriptionRequest;
use App\Http\Resources\Api\V1\Admin\AdminTenantSubscriptionResource;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Admin\AdminSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 11 — admin subscription assignment/update for a tenant. Platform admin
 * only. Manages the subscription foundation (plan + status/date window) and
 * audit-logs every mutation. Never performs real billing or charges.
 */
class AdminTenantSubscriptionController extends Controller
{
    public function __construct(
        private readonly AdminSubscriptionService $subscriptions,
    ) {}

    public function index(Tenant $tenant): AnonymousResourceCollection
    {
        $rows = $tenant->tenantSubscriptions()
            ->with('plan')
            ->orderByDesc('id')
            ->get();

        return AdminTenantSubscriptionResource::collection($rows)->additional([
            'meta' => [
                'tenant_id' => $tenant->id,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function store(StoreAdminTenantSubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->subscriptions->assign(
            actor: $request->user(),
            tenant: $tenant,
            data: $request->validated(),
            request: $request,
        );

        return AdminTenantSubscriptionResource::make($subscription->load('plan'))
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateAdminTenantSubscriptionRequest $request,
        Tenant $tenant,
        TenantSubscription $subscription,
    ): JsonResponse {
        abort_if((int) $subscription->tenant_id !== (int) $tenant->id, Response::HTTP_NOT_FOUND);

        $updated = $this->subscriptions->update(
            actor: $request->user(),
            tenant: $tenant,
            subscription: $subscription,
            data: $request->validated(),
            request: $request,
        );

        return AdminTenantSubscriptionResource::make($updated->load('plan'))
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
