<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportOps\SupportTimelineQueryRequest;
use App\Models\Tenant;
use App\Services\SupportOperations\SupportAndroidRuntimeViewerService;
use App\Services\SupportOperations\SupportBillingViewerService;
use App\Services\SupportOperations\SupportDiagnosticTimelineService;
use App\Services\SupportOperations\SupportEntitlementViewerService;
use App\Services\SupportOperations\SupportGovernanceAuditService;
use App\Services\SupportOperations\SupportOnboardingViewerService;
use App\Services\SupportOperations\SupportPaymentViewerService;
use App\Services\SupportOperations\SupportTenantHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 35 — the platform-admin, READ-ONLY support console surface
 * (SUP-R001/R003/R004). Every method reads through a governed viewer/health
 * service and returns redacted, aggregate-safe data. No method mutates any
 * tenant, billing, payment, entitlement, onboarding or device state.
 */
class AdminSupportConsoleController extends Controller
{
    public function __construct(
        private readonly SupportTenantHealthService $health,
        private readonly SupportDiagnosticTimelineService $timeline,
        private readonly SupportBillingViewerService $billing,
        private readonly SupportPaymentViewerService $payment,
        private readonly SupportEntitlementViewerService $entitlement,
        private readonly SupportOnboardingViewerService $onboarding,
        private readonly SupportAndroidRuntimeViewerService $androidRuntime,
        private readonly SupportGovernanceAuditService $governance,
    ) {}

    public function tenants(Request $request): JsonResponse
    {
        $query = Tenant::query()->orderByDesc('id');
        if ($request->filled('code')) {
            $query->where('code', (string) $request->input('code'));
        }
        $limit = max(1, min((int) $request->input('limit', 25), 100));

        return response()->json([
            'data' => $query->limit($limit)->get()->map(fn (Tenant $t) => $this->health->briefStatus($t))->all(),
        ]);
    }

    public function health(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->health->overview($tenant)]);
    }

    public function timeline(SupportTimelineQueryRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->timeline->build($tenant, [
                'category' => $request->input('category'),
                'source' => $request->input('source'),
                'since' => $request->input('since'),
                'limit' => $request->input('limit'),
            ]),
        ]);
    }

    public function billing(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->billing->summary($tenant->id)]);
    }

    public function payments(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->payment->summary($tenant->id)]);
    }

    public function entitlements(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->entitlement->summary($tenant->id)]);
    }

    public function onboarding(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->onboarding->summary($tenant->id)]);
    }

    public function androidRuntime(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => array_merge(
                $this->androidRuntime->summary($tenant->id),
                ['sync_failures' => $this->androidRuntime->syncFailures($tenant->id)],
            ),
        ]);
    }

    public function governance(): JsonResponse
    {
        return response()->json(['data' => $this->governance->evaluate()]);
    }
}
