<?php

namespace App\Services\TenantOnboarding;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sprint 33 — provisions the tenant's owner/admin user (ROLE_TENANT_OWNER),
 * enforcing the Sprint 32 user limit via canCreateUser BEFORE creation (ONB-R009/
 * R013). The password is a random write-only secret: it is hashed and NEVER
 * stored in a run/step/audit record, returned by an API, or printed (ONB-R024).
 * Idempotent: an existing owner for the tenant is reused (ONB-R021).
 */
class OwnerAdminProvisioningService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
        private readonly OnboardingAuditService $audit,
    ) {}

    public function provision(Tenant $tenant, Store $branch, OnboardingRequestData $data, ?User $actor = null): User
    {
        $existing = $tenant->users()->where('role', User::ROLE_TENANT_OWNER)->orderBy('id')->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $decision = $this->entitlements->canCreateUser($tenant, $actor);

        if ($decision->denied()) {
            $this->audit->recordEntitlementDenial($tenant, $decision, $actor, 'onboarding.owner_admin', [
                'step' => 'provision_owner_admin',
            ]);

            throw new OnboardingException(
                'DENIED_ENTITLEMENT',
                "Owner/admin denied by entitlement gate: {$decision->reasonCode}.",
            );
        }

        return User::query()->create([
            'name' => $data->ownerName !== '' ? $data->ownerName : 'Owner',
            'email' => $this->ownerEmail($tenant, $data),
            'phone' => $data->ownerPhone,
            // Write-only random secret; hashed and never surfaced anywhere.
            'password' => Hash::make(Str::password(20)),
            'tenant_id' => $tenant->id,
            'store_id' => $branch->id,
            'role' => User::ROLE_TENANT_OWNER,
            'is_active' => true,
        ]);
    }

    private function ownerEmail(Tenant $tenant, OnboardingRequestData $data): string
    {
        if ($data->ownerEmail !== null && $data->ownerEmail !== '') {
            return $data->ownerEmail;
        }

        // Deterministic per-tenant placeholder so a retry does not duplicate.
        return 'owner+'.Str::lower((string) $tenant->code).'@tenant.local';
    }
}
