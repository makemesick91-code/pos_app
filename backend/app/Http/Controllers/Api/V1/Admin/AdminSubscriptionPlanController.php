<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminSubscriptionPlanRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminSubscriptionPlanRequest;
use App\Http\Resources\Api\V1\Admin\AdminSubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\Admin\AdminPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 11 — admin subscription plan management. Platform admin only. Supports
 * list/create/update/deactivate; there is no hard delete. Every mutation is
 * audit-logged.
 */
class AdminSubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly AdminPlanService $plans,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return AdminSubscriptionPlanResource::collection($this->plans->list())->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }

    public function store(StoreAdminSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($request->user(), $request->validated(), $request);

        return AdminSubscriptionPlanResource::make($plan)
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateAdminSubscriptionPlanRequest $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan = $this->plans->update($request->user(), $plan, $request->validated(), $request);

        return AdminSubscriptionPlanResource::make($plan)
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function deactivate(SubscriptionPlan $plan): JsonResponse
    {
        $plan = $this->plans->deactivate(request()->user(), $plan, request());

        return AdminSubscriptionPlanResource::make($plan)
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
