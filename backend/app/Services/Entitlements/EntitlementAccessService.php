<?php

namespace App\Services\Entitlements;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPlan\FeatureEntitlementService;
use App\Services\TenantPlan\TenantPlanResolver;
use App\Services\TenantPlan\TenantUsageLimitService;

/**
 * Sprint 32 — the single runtime entitlement gate (ENT-R003). Every runtime
 * access question (create a branch/user/cashier/device/outlet/register, use a
 * premium feature, run an export/report, write, read) is answered here by
 * composing, in order:
 *
 *   plan (TenantPlanResolver, fail-closed on unknown — ENT-R001/R002)
 *     → billing/subscription/lifecycle write access (EntitlementBillingStateService)
 *     → feature entitlement (FeatureEntitlementService)
 *     → usage limit (TenantUsageLimitService)
 *
 * Manual suspension always wins (ENT-R013) and a paid invoice never lifts it
 * (ENT-R014) because the billing state service consults the suspension source of
 * truth first. Every denied / degraded decision is audit-logged with redacted
 * metadata (ENT-R018). The decision is deterministic and explainable (ENT-R019).
 */
class EntitlementAccessService
{
    public function __construct(
        private readonly TenantPlanResolver $planResolver,
        private readonly EntitlementBillingStateService $billing,
        private readonly TenantUsageLimitService $usageLimits,
        private readonly FeatureEntitlementService $features,
        private readonly EntitlementAuditService $audit,
    ) {}

    public function canCreateBranch(Tenant $tenant, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateResourceCreation($tenant, 'branch', $actor);
    }

    public function canCreateUser(Tenant $tenant, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateResourceCreation($tenant, 'user', $actor);
    }

    public function canCreateCashier(Tenant $tenant, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateResourceCreation($tenant, 'cashier', $actor);
    }

    public function canRegisterDevice(Tenant $tenant, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateResourceCreation($tenant, 'device', $actor);
    }

    public function canCreateOutletOrRegister(Tenant $tenant, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateResourceCreation($tenant, 'outlet', $actor);
    }

    public function canUseFeature(Tenant $tenant, string $featureKey, ?User $actor = null): EntitlementDecision
    {
        $planKey = $this->resolvePlanKey($tenant);
        if ($planKey === null) {
            return $this->audited($tenant, $this->unknownPlanDecision($featureKey, 'feature', 'use'), $actor);
        }

        $entitled = $this->features->isEntitled($tenant, $featureKey);
        $decision = new EntitlementDecision(
            allowed: $entitled,
            status: $entitled ? EntitlementDecision::STATUS_ALLOWED : EntitlementDecision::STATUS_DENIED,
            reasonCode: $entitled ? 'ALLOWED_ACTIVE_PAID' : 'FEATURE_NOT_IN_PLAN',
            message: $this->message($entitled ? 'ALLOWED_ACTIVE_PAID' : 'FEATURE_NOT_IN_PLAN'),
            entitlementKey: $featureKey,
            resourceType: 'feature',
            action: 'use',
            planCode: $planKey,
        );

        return $this->audited($tenant, $decision, $actor);
    }

    public function canUseExport(Tenant $tenant, string $exportKey, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateGovernedRead($tenant, 'exports', $exportKey, 'EXPORT_NOT_IN_PLAN', 'export', $actor);
    }

    public function canUseReport(Tenant $tenant, string $reportKey, ?User $actor = null): EntitlementDecision
    {
        return $this->evaluateGovernedRead($tenant, 'reports', $reportKey, 'REPORT_NOT_IN_PLAN', 'report', $actor);
    }

    /**
     * Generic write gate (billing/subscription/lifecycle dimension). Used by the
     * EnsureTenantCanWrite middleware for mutating operational routes.
     */
    public function canWrite(Tenant $tenant, ?User $actor = null, ?string $context = null): EntitlementDecision
    {
        $planKey = $this->resolvePlanKey($tenant);
        if ($planKey === null) {
            return $this->audited($tenant, $this->unknownPlanDecision($context, 'write', 'write'), $actor);
        }

        $decision = $this->billing->resolveWriteAccess($tenant)
            ->withContext($context, $context ?? 'write', 'write');
        $decision = $this->withPlan($decision, $planKey);

        return $this->audited($tenant, $decision, $actor);
    }

    public function canRead(Tenant $tenant, ?User $actor = null, ?string $context = null): EntitlementDecision
    {
        $decision = $this->billing->resolveReadAccess($tenant)
            ->withContext($context, $context ?? 'read', 'read');

        return $this->audited($tenant, $decision, $actor);
    }

    /**
     * A read-only, deterministic explanation of a decision (no audit write).
     *
     * @return array<string, mixed>
     */
    public function explain(Tenant $tenant, string $entitlementKey, string $action, ?string $context = null): array
    {
        $decision = match ($action) {
            'branch', 'user', 'cashier', 'device', 'outlet', 'register' => $this->evaluateResourceCreation($tenant, $action, null, audit: false),
            'feature' => $this->explainFeature($tenant, $entitlementKey),
            'export' => $this->evaluateGovernedRead($tenant, 'exports', $entitlementKey, 'EXPORT_NOT_IN_PLAN', 'export', null, audit: false),
            'report' => $this->evaluateGovernedRead($tenant, 'reports', $entitlementKey, 'REPORT_NOT_IN_PLAN', 'report', null, audit: false),
            'read' => $this->billing->resolveReadAccess($tenant),
            default => $this->billing->resolveWriteAccess($tenant),
        };

        return $decision->toArray();
    }

    // --- internals -----------------------------------------------------------

    private function evaluateResourceCreation(Tenant $tenant, string $limitAlias, ?User $actor, bool $audit = true): EntitlementDecision
    {
        $config = (array) config('entitlement_governance.limits.'.$limitAlias, []);
        $resource = (string) ($config['resource'] ?? $limitAlias);
        $action = (string) ($config['action'] ?? 'create');
        $limitKey = (string) ($config['limit_key'] ?? '');

        $planKey = $this->resolvePlanKey($tenant);
        if ($planKey === null) {
            $decision = $this->unknownPlanDecision($limitKey, $resource, $action);

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Billing/subscription/lifecycle write access first (manual suspension
        // wins). A read-only/denied billing state blocks new resource creation.
        $billing = $this->billing->resolveWriteAccess($tenant);
        if ($billing->denied()) {
            $decision = $this->withPlan($billing->withContext($limitKey, $resource, $action), $planKey);

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Usage limit (Sprint 26 authoritative meter). At/over cap → OVER_QUOTA.
        $usageDecision = $this->usageLimits->canUse($tenant, $limitKey, 1);
        if ($usageDecision->exceeded()) {
            $decision = new EntitlementDecision(
                allowed: false,
                status: EntitlementDecision::STATUS_DENIED,
                reasonCode: 'OVER_QUOTA',
                message: $this->message('OVER_QUOTA'),
                entitlementKey: $limitKey,
                resourceType: $resource,
                action: $action,
                planCode: $planKey,
                currentUsage: $usageDecision->current,
                limitValue: $usageDecision->limit,
                billingState: $billing->billingState,
                subscriptionState: $billing->subscriptionState,
            );

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Allowed (propagate degraded/within-grace so it is still audited).
        $degraded = $billing->degraded;
        $decision = new EntitlementDecision(
            allowed: true,
            status: $degraded ? EntitlementDecision::STATUS_DEGRADED : EntitlementDecision::STATUS_ALLOWED,
            reasonCode: $billing->reasonCode,
            message: $billing->message,
            entitlementKey: $limitKey,
            resourceType: $resource,
            action: $action,
            planCode: $planKey,
            currentUsage: $usageDecision->current,
            limitValue: $usageDecision->limit,
            billingState: $billing->billingState,
            subscriptionState: $billing->subscriptionState,
            degraded: $degraded,
        );

        return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
    }

    private function evaluateGovernedRead(Tenant $tenant, string $group, string $key, string $notInPlanCode, string $resource, ?User $actor, bool $audit = true): EntitlementDecision
    {
        // Look the group up literally: export/report keys contain dots
        // (e.g. reports.daily-sales.csv), so config() dot-notation would wrongly
        // treat them as nested paths (mirrors the Sprint 27 dot-path fix).
        $groupConfig = (array) config('entitlement_governance.'.$group, []);
        $config = (array) ($groupConfig[$key] ?? []);
        $entitlement = (string) ($config['entitlement'] ?? '');
        $limitKey = $config['limit_key'] ?? null;

        $planKey = $this->resolvePlanKey($tenant);
        if ($planKey === null) {
            $decision = $this->unknownPlanDecision($key, $resource, 'use');

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Manual suspension / unpaid-past-grace blocks a governed export/report.
        $billing = $this->billing->resolveWriteAccess($tenant);
        if ($billing->denied()) {
            $decision = $this->withPlan($billing->withContext($key, $resource, 'use'), $planKey);

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Plan entitlement for the export/report feature key.
        if ($entitlement !== '' && ! $this->features->isEntitled($tenant, $entitlement)) {
            $decision = new EntitlementDecision(
                allowed: false,
                status: EntitlementDecision::STATUS_DENIED,
                reasonCode: $notInPlanCode,
                message: $this->message($notInPlanCode),
                entitlementKey: $key,
                resourceType: $resource,
                action: 'use',
                planCode: $planKey,
                billingState: $billing->billingState,
                subscriptionState: $billing->subscriptionState,
            );

            return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
        }

        // Metered usage limit (e.g. reports.exports.monthly).
        if (is_string($limitKey) && $limitKey !== '') {
            $usageDecision = $this->usageLimits->canUse($tenant, $limitKey, 1);
            if ($usageDecision->exceeded()) {
                $decision = new EntitlementDecision(
                    allowed: false,
                    status: EntitlementDecision::STATUS_DENIED,
                    reasonCode: 'USAGE_LIMIT_EXCEEDED',
                    message: $this->message('USAGE_LIMIT_EXCEEDED'),
                    entitlementKey: $limitKey,
                    resourceType: $resource,
                    action: 'use',
                    planCode: $planKey,
                    currentUsage: $usageDecision->current,
                    limitValue: $usageDecision->limit,
                    billingState: $billing->billingState,
                    subscriptionState: $billing->subscriptionState,
                );

                return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
            }
        }

        $degraded = $billing->degraded;
        $decision = new EntitlementDecision(
            allowed: true,
            status: $degraded ? EntitlementDecision::STATUS_DEGRADED : EntitlementDecision::STATUS_ALLOWED,
            reasonCode: $billing->reasonCode,
            message: $billing->message,
            entitlementKey: $key,
            resourceType: $resource,
            action: 'use',
            planCode: $planKey,
            billingState: $billing->billingState,
            subscriptionState: $billing->subscriptionState,
            degraded: $degraded,
        );

        return $audit ? $this->audited($tenant, $decision, $actor) : $decision;
    }

    private function explainFeature(Tenant $tenant, string $featureKey): EntitlementDecision
    {
        $planKey = $this->resolvePlanKey($tenant);
        if ($planKey === null) {
            return $this->unknownPlanDecision($featureKey, 'feature', 'use');
        }

        $entitled = $this->features->isEntitled($tenant, $featureKey);

        return new EntitlementDecision(
            allowed: $entitled,
            status: $entitled ? EntitlementDecision::STATUS_ALLOWED : EntitlementDecision::STATUS_DENIED,
            reasonCode: $entitled ? 'ALLOWED_ACTIVE_PAID' : 'FEATURE_NOT_IN_PLAN',
            message: $this->message($entitled ? 'ALLOWED_ACTIVE_PAID' : 'FEATURE_NOT_IN_PLAN'),
            entitlementKey: $featureKey,
            resourceType: 'feature',
            action: 'use',
            planCode: $planKey,
        );
    }

    /**
     * Resolve the tenant's plan key, or null when it fails closed (ENT-R002).
     */
    private function resolvePlanKey(Tenant $tenant): ?string
    {
        $plan = $this->planResolver->resolve($tenant);
        $key = $plan->planKey;

        if ($key === '' && (bool) config('entitlement_governance.fail_closed_on_unknown_plan', true)) {
            return null;
        }

        return $key === '' ? null : $key;
    }

    private function unknownPlanDecision(?string $entitlementKey, ?string $resource, ?string $action): EntitlementDecision
    {
        return new EntitlementDecision(
            allowed: false,
            status: EntitlementDecision::STATUS_DENIED,
            reasonCode: 'UNKNOWN_PLAN',
            message: $this->message('UNKNOWN_PLAN'),
            entitlementKey: $entitlementKey,
            resourceType: $resource,
            action: $action,
        );
    }

    private function withPlan(EntitlementDecision $decision, string $planKey): EntitlementDecision
    {
        return new EntitlementDecision(
            allowed: $decision->allowed,
            status: $decision->status,
            reasonCode: $decision->reasonCode,
            message: $decision->message,
            entitlementKey: $decision->entitlementKey,
            resourceType: $decision->resourceType,
            action: $decision->action,
            planCode: $planKey,
            currentUsage: $decision->currentUsage,
            limitValue: $decision->limitValue,
            billingState: $decision->billingState,
            subscriptionState: $decision->subscriptionState,
            degraded: $decision->degraded,
            readOnly: $decision->readOnly,
            metadata: $decision->metadata,
        );
    }

    private function audited(Tenant $tenant, EntitlementDecision $decision, ?User $actor): EntitlementDecision
    {
        $this->audit->record($tenant, $decision, $actor, $decision->resourceType, null);

        return $decision;
    }

    private function message(string $reasonCode): string
    {
        return (string) (config('entitlement_governance.reason_codes.'.$reasonCode) ?? 'Entitlement decision.');
    }
}
