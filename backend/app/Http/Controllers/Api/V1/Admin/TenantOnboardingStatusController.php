<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantOnboardingStatusResource;
use App\Models\Tenant;
use App\Services\Onboarding\TenantOnboardingChecklistService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 12 — backend-generated onboarding status/checklist for a tenant.
 * Platform admin only. The checklist is recomputed from tenant state on every
 * request and never trusted from the client.
 */
class TenantOnboardingStatusController extends Controller
{
    public function __construct(
        private readonly TenantOnboardingChecklistService $checklist,
    ) {}

    public function show(Tenant $tenant): JsonResource
    {
        $tenant->setAttribute('onboarding_checklist', $this->checklist->buildForTenant($tenant));

        return TenantOnboardingStatusResource::make($tenant)->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }
}
