<?php

namespace App\Services\SupportOperations;

use App\Models\TenantEntitlementDecision;

/**
 * Sprint 35 — read-only entitlement state + blocked/denied action explorer
 * (SUP-R010/R016/R021).
 *
 * Sources from the Sprint 32 tenant_entitlement_decisions audit ledger only. It
 * NEVER calls EntitlementAccessService to make/alter an access decision and never
 * unlocks a paid entitlement — it is a read of what already happened. Reason codes
 * are safe/enumerable by construction (they come from the Sprint 32 recorder).
 */
class SupportEntitlementViewerService
{
    public function summary(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, (int) config('support_operations_governance.blocked_action_explorer.default_limit', 100)));

        $decisions = TenantEntitlementDecision::query()
            ->forTenant($tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $byDecision = [];
        foreach ($decisions as $decision) {
            $byDecision[$decision->decision] = ($byDecision[$decision->decision] ?? 0) + 1;
        }

        $latest = $decisions->first();

        return [
            'read_only' => true,
            'decision_count' => $decisions->count(),
            'by_decision' => $byDecision,
            'latest_billing_state' => optional($latest)->billing_state,
            'latest_subscription_state' => optional($latest)->subscription_state,
            'latest_plan_code' => optional($latest)->plan_code,
            'denied' => $decisions
                ->whereIn('decision', [
                    TenantEntitlementDecision::DECISION_DENIED,
                    TenantEntitlementDecision::DECISION_READ_ONLY,
                    TenantEntitlementDecision::DECISION_DEGRADED,
                ])
                ->take(20)
                ->map(fn (TenantEntitlementDecision $d) => [
                    'entitlement_key' => $d->entitlement_key,
                    'resource_type' => $d->resource_type,
                    'action' => $d->action,
                    'decision' => $d->decision,
                    'reason_code' => $d->reason_code,
                    'current_usage' => $d->current_usage,
                    'limit_value' => $d->limit_value,
                    'created_at' => optional($d->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
