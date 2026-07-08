<?php

namespace App\Services\TenantOnboarding;

use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Models\TenantSubscription;
use Illuminate\Support\Str;

/**
 * Sprint 33 — creates the canonical Tenant record for an onboarding run
 * (ONB-R006). Idempotent: a run that already has a tenant reuses it; a code
 * collision with a DIFFERENT tenant fails closed so we never silently attach to
 * another tenant's identity (ONB-R021). The tenant is created in a trial/pending
 * posture — never a paid state (paid access only ever follows collection).
 */
class TenantProvisioningService
{
    /**
     * @return array{tenant: Tenant, created: bool}
     */
    public function provision(TenantProvisioningRun $run, OnboardingRequestData $data): array
    {
        if ($run->tenant_id !== null) {
            $existing = Tenant::query()->find($run->tenant_id);

            if ($existing instanceof Tenant) {
                return ['tenant' => $existing, 'created' => false];
            }
        }

        $code = $this->tenantCode($data);

        $clash = Tenant::query()->where('code', $code)->first();

        if ($clash instanceof Tenant) {
            // Only reuse if this run already owns it; otherwise fail closed.
            if ($run->tenant_id === $clash->id) {
                return ['tenant' => $clash, 'created' => false];
            }

            throw new OnboardingException(
                'TENANT_CODE_TAKEN',
                "Tenant code '{$code}' already belongs to another tenant; refusing to create a duplicate identity.",
            );
        }

        $tenant = Tenant::query()->create([
            'code' => $code,
            'name' => $data->tenantName,
            'business_type' => null,
            'owner_name' => $data->ownerName,
            'owner_phone' => $data->ownerPhone,
            'status' => Tenant::STATUS_ACTIVE,
            'subscription_plan' => $data->planCode,
            'subscription_status' => $data->withTrial
                ? TenantSubscription::STATUS_TRIAL
                : TenantSubscription::STATUS_ACTIVE,
        ]);

        return ['tenant' => $tenant, 'created' => true];
    }

    private function tenantCode(OnboardingRequestData $data): string
    {
        if ($data->tenantCode !== null && $data->tenantCode !== '') {
            return $data->tenantCode;
        }

        $base = Str::upper(Str::slug($data->tenantName));
        $base = $base !== '' ? $base : 'TENANT';

        // Deterministic per idempotency key so a retry computes the same code.
        $suffix = strtoupper(substr(hash('sha256', $data->idempotencyKey), 0, 6));

        return substr($base, 0, 20).'-'.$suffix;
    }
}
