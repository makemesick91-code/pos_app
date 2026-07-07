<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexTenantOnboardingRequest;
use App\Http\Requests\Api\V1\Admin\StoreTenantOnboardingRequest;
use App\Http\Resources\Api\V1\Admin\TenantOnboardingRunResource;
use App\Models\TenantOnboardingRun;
use App\Services\Onboarding\TenantOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 12 — platform-admin tenant onboarding. Creates a tenant + default store
 * + owner user + subscription (and optional demo data) in one transaction,
 * idempotent by onboarding_reference. Platform admin only (platform.admin). No
 * real billing, no invites, no impersonation. Owner password is never returned.
 */
class TenantOnboardingController extends Controller
{
    public function __construct(
        private readonly TenantOnboardingService $onboarding,
    ) {}

    public function store(StoreTenantOnboardingRequest $request): JsonResponse
    {
        $result = $this->onboarding->onboard(
            actor: $request->user(),
            data: $request->validated(),
            request: $request,
        );

        /** @var TenantOnboardingRun $run */
        $run = $result['run'];
        $run->loadMissing('tenantSubscription');

        $status = $result['replay'] ? Response::HTTP_OK : Response::HTTP_CREATED;

        return TenantOnboardingRunResource::make($run)
            ->additional([
                'meta' => [
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                    'idempotent_replay' => $result['replay'],
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }

    public function index(IndexTenantOnboardingRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $limit = (int) ($filters['limit'] ?? 50);

        $runs = TenantOnboardingRun::query()
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['tenant_id']), fn ($q) => $q->where('tenant_id', (int) $filters['tenant_id']))
            ->when(! empty($filters['q']), function ($q) use ($filters): void {
                $term = (string) $filters['q'];
                $q->where(function ($inner) use ($term): void {
                    $inner->where('tenant_name', 'like', "%{$term}%")
                        ->orWhere('onboarding_reference', 'like', "%{$term}%")
                        ->orWhere('owner_email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(max(1, min($limit, 100)));

        return TenantOnboardingRunResource::collection($runs)->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }

    public function show(TenantOnboardingRun $onboardingRun): JsonResource
    {
        $onboardingRun->loadMissing('tenantSubscription');

        return TenantOnboardingRunResource::make($onboardingRun)->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }
}
