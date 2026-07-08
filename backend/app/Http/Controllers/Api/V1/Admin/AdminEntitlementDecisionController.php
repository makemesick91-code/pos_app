<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantEntitlementDecision;
use App\Services\Entitlements\EntitlementAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 32 — READ-ONLY platform-admin access to the entitlement decision log
 * (denied / degraded / read_only / bypassed decisions, ENT-R018). Rows are
 * already redacted at write time, so this surface can never leak secrets or PII
 * (ENT-R020). No mutation route.
 */
class AdminEntitlementDecisionController extends Controller
{
    public function __construct(
        private readonly EntitlementAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TenantEntitlementDecision::query()->latest('id');

        if ($request->filled('tenant_id')) {
            $query->forTenant((int) $request->integer('tenant_id'));
        }
        if ($request->filled('decision')) {
            $query->where('decision', (string) $request->string('decision'));
        }
        if ($request->filled('reason_code')) {
            $query->where('reason_code', (string) $request->string('reason_code'));
        }

        $decisions = $query->paginate(min(100, (int) $request->integer('per_page', 25)));

        return response()->json([
            'data' => collect($decisions->items())->map(fn (TenantEntitlementDecision $d) => $this->present($d)),
            'meta' => [
                'current_page' => $decisions->currentPage(),
                'last_page' => $decisions->lastPage(),
                'total' => $decisions->total(),
            ],
        ]);
    }

    public function show(TenantEntitlementDecision $decision): JsonResponse
    {
        return response()->json(['data' => $this->present($decision)]);
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->filled('tenant_id') ? (int) $request->integer('tenant_id') : null;

        return response()->json(['data' => $this->audit->summary($tenantId)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TenantEntitlementDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'tenant_id' => $decision->tenant_id,
            'entitlement_key' => $decision->entitlement_key,
            'resource_type' => $decision->resource_type,
            'action' => $decision->action,
            'decision' => $decision->decision,
            'reason_code' => $decision->reason_code,
            'plan_code' => $decision->plan_code,
            'current_usage' => $decision->current_usage,
            'limit_value' => $decision->limit_value,
            'billing_state' => $decision->billing_state,
            'subscription_state' => $decision->subscription_state,
            'created_at' => $decision->created_at,
        ];
    }
}
