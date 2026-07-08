<?php

namespace App\Services\TenantOnboarding;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Support\Str;

/**
 * Sprint 33 — provisions the tenant's first branch (a Store in this codebase;
 * there is no separate Branch model). It enforces the Sprint 32 branch limit via
 * EntitlementAccessService::canCreateBranch BEFORE creating the store (ONB-R008/
 * R013). Idempotent: an existing first store for the tenant is reused (ONB-R021).
 */
class FirstBranchProvisioningService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
        private readonly OnboardingAuditService $audit,
    ) {}

    public function provision(Tenant $tenant, OnboardingRequestData $data, ?User $actor = null): Store
    {
        $existing = $tenant->stores()->orderBy('id')->first();

        if ($existing instanceof Store) {
            return $existing;
        }

        $decision = $this->entitlements->canCreateBranch($tenant, $actor);

        if ($decision->denied()) {
            $this->audit->recordEntitlementDenial($tenant, $decision, $actor, 'onboarding.first_branch', [
                'step' => 'provision_first_branch',
            ]);

            throw new OnboardingException(
                'DENIED_ENTITLEMENT',
                "First branch denied by entitlement gate: {$decision->reasonCode}.",
            );
        }

        return Store::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data->firstBranchName !== '' ? $data->firstBranchName : ($data->tenantName.' Pusat'),
            'code' => $this->branchCode($tenant, $data),
            'is_active' => true,
        ]);
    }

    private function branchCode(Tenant $tenant, OnboardingRequestData $data): string
    {
        if ($data->firstBranchCode !== null && $data->firstBranchCode !== '') {
            return $data->firstBranchCode;
        }

        return Str::upper(Str::slug((string) $tenant->code)).'-01';
    }
}
