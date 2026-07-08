<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Entitlements\EntitlementBillingStateService;
use App\Services\Entitlements\EntitlementGovernanceAuditService;
use App\Services\Entitlements\EntitlementSummaryService;
use App\Services\Entitlements\EntitlementUsageService;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 32 — READ-ONLY platform-admin visibility into runtime entitlement
 * enforcement (ENT-R022). Exposes the configured plan catalogue, a per-tenant
 * entitlement + usage + billing-access summary, and the governance posture. No
 * mutation route exists here; entitlement state is never changed via HTTP
 * (tenant_route_can_mutate_entitlement_state_allowed=false). All output is
 * redacted/safe (ENT-R020).
 */
class AdminTenantEntitlementAccessController extends Controller
{
    public function __construct(
        private readonly EntitlementSummaryService $summary,
        private readonly EntitlementUsageService $usage,
        private readonly EntitlementBillingStateService $billing,
        private readonly EntitlementGovernanceAuditService $governance,
    ) {}

    public function planSummary(): JsonResponse
    {
        return response()->json(['data' => $this->summary->planSummary()]);
    }

    public function governanceSummary(): JsonResponse
    {
        $signals = $this->governance->evaluate();

        return response()->json([
            'data' => [
                'passes' => $this->governance->passes(),
                'signals' => $signals,
            ],
        ]);
    }

    public function tenantSummary(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->summary->tenantSummary($tenant)]);
    }

    public function usageSummary(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'usage' => $this->usage->summary($tenant),
            ],
        ]);
    }

    public function billingState(Tenant $tenant): JsonResponse
    {
        $write = $this->billing->resolveWriteAccess($tenant);

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'billing_state' => $write->billingState,
                'subscription_state' => $write->subscriptionState,
                'write_allowed' => $write->allowed,
                'reason_code' => $write->reasonCode,
                'degraded' => $write->degraded,
                'read_only' => $write->readOnly,
            ],
        ]);
    }
}
