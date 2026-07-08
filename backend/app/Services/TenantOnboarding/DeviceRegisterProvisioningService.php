<?php

namespace App\Services\TenantOnboarding;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Support\Str;

/**
 * Sprint 33 — prepares the first register/device SETUP for a new tenant.
 *
 * There is no long-lived device credential created here: the real device
 * activates later from the Android app (Sprint 10 device.registered). We only
 * mint a ONE-TIME setup token, hash it, and return a non-reversible fingerprint
 * (never the raw token, never a stored raw token — ONB-R011/R024). The device
 * limit is enforced via canRegisterDevice BEFORE any setup is prepared.
 *
 * The "first register" is the first store (there is no separate register table),
 * so `first_register_id` on the run points at the store.
 */
class DeviceRegisterProvisioningService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
        private readonly OnboardingAuditService $audit,
    ) {}

    /**
     * @return array{register_id: int, setup_fingerprint: string, prepared: bool}
     */
    public function prepare(Tenant $tenant, Store $branch, ?User $actor = null): array
    {
        $decision = $this->entitlements->canRegisterDevice($tenant, $actor);

        if ($decision->denied()) {
            $this->audit->recordEntitlementDenial($tenant, $decision, $actor, 'onboarding.device_register', [
                'step' => 'prepare_device_register',
            ]);

            throw new OnboardingException(
                'DENIED_ENTITLEMENT',
                "Device/register setup denied by entitlement gate: {$decision->reasonCode}.",
            );
        }

        // One-time raw token exists only in this local scope; it is hashed and
        // never persisted, returned, or logged. The fingerprint is a short,
        // non-reversible prefix of the hash — safe for the audit trail.
        $rawToken = Str::random(40);
        $hash = hash('sha256', $rawToken);
        $fingerprint = substr($hash, 0, 8);
        unset($rawToken, $hash);

        return [
            'register_id' => (int) $branch->id,
            'setup_fingerprint' => $fingerprint,
            'prepared' => true,
        ];
    }
}
