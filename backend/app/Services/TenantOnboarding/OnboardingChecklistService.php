<?php

namespace App\Services\TenantOnboarding;

use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;

/**
 * Sprint 33 — computes a DETERMINISTIC, EXPLAINABLE onboarding checklist entirely
 * from backend state (ONB-R022). Every item is derived from a DB existence query
 * or the run's own columns; nothing is trusted from the client. Output is safe:
 * it carries booleans and stable reason codes only — never secrets or PII.
 */
class OnboardingChecklistService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(TenantProvisioningRun $run): array
    {
        $tenant = $run->tenant_id !== null ? Tenant::query()->find($run->tenant_id) : null;

        $items = [];

        $items['tenant_created'] = $this->item($tenant !== null);
        $items['plan_resolved'] = $this->item($run->resolved_plan_code !== null && $run->resolved_plan_code !== '');
        $items['trial_active'] = $this->item($this->trialActive($tenant));
        $items['first_branch_created'] = $this->item($tenant !== null && $tenant->stores()->exists());
        $items['owner_admin_created'] = $this->item(
            $tenant !== null && $tenant->users()->where('role', User::ROLE_TENANT_OWNER)->exists()
        );
        $items['cashier_provisioned'] = $this->cashierItem($run, $tenant);
        $items['register_device_prepared'] = $this->registerItem($run);
        $items['seed_data_completed'] = $this->item(
            $tenant !== null && ProductCategory::query()->where('tenant_id', $tenant->id)->exists()
        );
        $items['invoice_ready'] = $this->invoiceItem($run);
        $items['payment_intent_ready'] = $this->paymentIntentItem($run);
        $items['entitlement_runtime_access_verified'] = $this->entitlementItem($tenant);

        $required = $this->requiredKeys();
        $complete = true;

        foreach ($required as $key) {
            if (($items[$key]['done'] ?? false) !== true) {
                $complete = false;
                break;
            }
        }

        return [
            'items' => $items,
            'required' => $required,
            'complete' => $complete,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function requiredKeys(): array
    {
        $keys = ['tenant_created', 'plan_resolved', 'first_branch_created', 'owner_admin_created', 'seed_data_completed', 'entitlement_runtime_access_verified'];

        if ((bool) config('onboarding_governance.provisioning.first_cashier_required', true)) {
            $keys[] = 'cashier_provisioned';
        }

        if ((bool) config('onboarding_governance.provisioning.device_register_setup_required', true)) {
            $keys[] = 'register_device_prepared';
        }

        return $keys;
    }

    private function trialActive(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return $tenant->tenantSubscriptions()
            ->whereIn('status', [TenantSubscription::STATUS_TRIAL, TenantSubscription::STATUS_ACTIVE])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function cashierItem(TenantProvisioningRun $run, ?Tenant $tenant): array
    {
        if (! (bool) config('onboarding_governance.provisioning.first_cashier_required', true)) {
            return $this->item(true, 'SKIPPED_CONFIG');
        }

        $done = $tenant !== null && $tenant->users()->where('role', User::ROLE_CASHIER)->exists();

        return $this->item($done);
    }

    /**
     * @return array<string, mixed>
     */
    private function registerItem(TenantProvisioningRun $run): array
    {
        if (! (bool) config('onboarding_governance.provisioning.device_register_setup_required', true)) {
            return $this->item(true, 'SKIPPED_CONFIG');
        }

        return $this->item($run->first_register_id !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceItem(TenantProvisioningRun $run): array
    {
        return $this->item($run->tenant_billing_invoice_id !== null, $run->tenant_billing_invoice_id !== null ? 'COMPLETED' : 'SKIPPED_REQUEST');
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentIntentItem(TenantProvisioningRun $run): array
    {
        return $this->item($run->payment_intent_id !== null, $run->payment_intent_id !== null ? 'COMPLETED' : 'SKIPPED_REQUEST');
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementItem(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return $this->item(false);
        }

        // Verify runtime access resolves through the Sprint 32 gate. A new
        // trial tenant should be able to read; this proves the chain is wired.
        $decision = $this->entitlements->canRead($tenant, null, 'onboarding.checklist');

        return $this->item($decision->allowed, $decision->reasonCode);
    }

    /**
     * @return array{done: bool, reason_code: string}
     */
    private function item(bool $done, ?string $reasonCode = null): array
    {
        return [
            'done' => $done,
            'reason_code' => $reasonCode ?? ($done ? 'COMPLETED' : 'PENDING'),
        ];
    }
}
