<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Services\TenantPlan\TenantPlanResolver;

/**
 * Sprint 30 — resolves the price an invoice is generated from (BIL-R003).
 *
 * The tenant's authoritative plan comes from the Sprint 26 TenantPlanResolver;
 * the amount comes from config/billing_governance.php (the pricing source of
 * truth). A tenant with no resolvable plan pricing NEVER yields a silent zero
 * invoice — the resolver throws a governance error unless the plan is explicitly
 * marked free. This service never reads price from client input.
 */
class TenantInvoicePricingService
{
    public function __construct(
        private readonly TenantPlanResolver $planResolver,
    ) {}

    /**
     * @return array{plan_key:string, amount:int, currency:string, interval:string, free:bool}
     */
    public function resolveForTenant(Tenant $tenant): array
    {
        $planKey = $this->planResolver->resolve($tenant)->planKey;

        return $this->resolveForPlanKey($planKey);
    }

    /**
     * @return array{plan_key:string, amount:int, currency:string, interval:string, free:bool}
     */
    public function resolveForPlanKey(string $planKey): array
    {
        $pricing = config('billing_governance.pricing.'.$planKey);

        if (! is_array($pricing)) {
            throw new BillingGovernanceException(
                'BILLING_NO_PLAN_PRICING',
                "No billing pricing configured for plan '{$planKey}'; refusing to generate an invoice.",
            );
        }

        if (($pricing['active'] ?? true) === false) {
            throw new BillingGovernanceException(
                'BILLING_PLAN_PRICING_INACTIVE',
                "Billing pricing for plan '{$planKey}' is inactive; refusing to generate an invoice.",
            );
        }

        $free = (bool) ($pricing['free'] ?? false);
        $amount = (int) ($pricing['amount'] ?? 0);

        if (! $free && $amount <= 0) {
            throw new BillingGovernanceException(
                'BILLING_ZERO_PRICE_NOT_FREE',
                "Plan '{$planKey}' has a non-positive price but is not marked free; refusing to generate a silent zero invoice.",
            );
        }

        return [
            'plan_key' => $planKey,
            'amount' => $amount,
            'currency' => (string) ($pricing['currency'] ?? config('billing_governance.default_currency', 'IDR')),
            'interval' => (string) ($pricing['interval'] ?? 'monthly'),
            'free' => $free,
        ];
    }
}
