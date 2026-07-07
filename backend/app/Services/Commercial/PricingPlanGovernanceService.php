<?php

namespace App\Services\Commercial;

use App\Models\SaasPackageCatalog;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20 — pricing / plan governance.
 *
 * Compares the SaaS package catalog pricing metadata against the existing
 * SubscriptionPlan foundation (Sprint 10) and derives a GO/WATCH/NO_GO decision.
 * This never charges a real customer, never creates a payment gateway
 * subscription, and never mutates TenantSubscription — SubscriptionPlan /
 * TenantSubscription / RegisteredDevice remain the runtime subscription
 * enforcement source.
 *
 * NO_GO — no active package, or an active package is missing price/currency/device
 *         limit governance metadata.
 * WATCH — active plans exist but no active package aligns with a subscription plan
 *         device limit (governance drift).
 * GO    — active packages carry complete pricing metadata and (when subscription
 *         plans exist) at least one aligns with a plan device limit.
 */
class PricingPlanGovernanceService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $active = SaasPackageCatalog::query()->active()->get();

        $incomplete = $active->filter(function (SaasPackageCatalog $p): bool {
            return $p->monthly_price === null
                || $p->currency === null || $p->currency === ''
                || $p->device_limit === null;
        })->pluck('package_code')->values()->all();

        $plansAvailable = Schema::hasTable('subscription_plans');
        $planDeviceLimits = [];
        if ($plansAvailable) {
            $planDeviceLimits = SubscriptionPlan::query()->active()->pluck('max_devices')->filter()->unique()->values()->all();
        }

        $alignedPackages = [];
        if ($planDeviceLimits !== []) {
            $alignedPackages = $active
                ->filter(fn (SaasPackageCatalog $p) => $p->device_limit !== null && in_array((int) $p->device_limit, array_map('intval', $planDeviceLimits), true))
                ->pluck('package_code')
                ->values()
                ->all();
        }

        $decision = self::DECISION_GO;
        $notes = [];

        if ($active->count() === 0) {
            $decision = self::DECISION_NO_GO;
            $notes[] = 'No active package to govern pricing for.';
        } elseif ($incomplete !== []) {
            $decision = self::DECISION_NO_GO;
            $notes[] = 'Active packages missing pricing metadata: '.implode(', ', $incomplete);
        } elseif ($planDeviceLimits !== [] && $alignedPackages === []) {
            $decision = self::DECISION_WATCH;
            $notes[] = 'No active package device limit aligns with a subscription plan device limit.';
        }

        return [
            'decision' => $decision,
            'active_packages' => $active->count(),
            'incomplete_pricing_packages' => $incomplete,
            'subscription_plans_available' => $plansAvailable,
            'plan_device_limits' => array_map('intval', $planDeviceLimits),
            'aligned_packages' => $alignedPackages,
            'notes' => $notes,
            'runtime_enforcement' => 'SubscriptionPlan/TenantSubscription/RegisteredDevice remain runtime enforcement.',
        ];
    }
}
