<?php

namespace App\Services\TenantOnboarding;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sprint 33 — provisions the tenant's first cashier user (ROLE_CASHIER) when the
 * run requests it, enforcing the Sprint 32 cashier limit via canCreateCashier
 * BEFORE creation (ONB-R010/R013). Password handling matches the owner service:
 * a random write-only secret, hashed, never surfaced (ONB-R024). Idempotent
 * (ONB-R021).
 */
class CashierProvisioningService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
        private readonly OnboardingAuditService $audit,
    ) {}

    public function provision(Tenant $tenant, Store $branch, OnboardingRequestData $data, ?User $actor = null): User
    {
        $existing = $tenant->users()
            ->where('role', User::ROLE_CASHIER)
            ->orderBy('id')
            ->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $decision = $this->entitlements->canCreateCashier($tenant, $actor);

        if ($decision->denied()) {
            $this->audit->recordEntitlementDenial($tenant, $decision, $actor, 'onboarding.first_cashier', [
                'step' => 'provision_first_cashier',
            ]);

            throw new OnboardingException(
                'DENIED_ENTITLEMENT',
                "First cashier denied by entitlement gate: {$decision->reasonCode}.",
            );
        }

        return User::query()->create([
            'name' => $data->firstCashierName !== null && $data->firstCashierName !== ''
                ? $data->firstCashierName
                : 'Kasir 1',
            'email' => 'cashier+'.Str::lower((string) $tenant->code).'@tenant.local',
            'password' => Hash::make(Str::password(20)),
            'tenant_id' => $tenant->id,
            'store_id' => $branch->id,
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);
    }
}
