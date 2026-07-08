<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantDeviceActivation;
use App\Services\AndroidRuntime\AndroidRuntimeGovernanceAuditService;
use App\Services\AndroidRuntime\AndroidRuntimeSummaryService;
use App\Services\AndroidRuntime\DeviceRevocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 34 — platform-admin read + device-support surface for the Android runtime
 * (ADR-R022/R028). Read endpoints return redacted, aggregate-safe data. The only
 * mutation is device revocation, which is audited. No route can unlock billing/
 * entitlement or mark an invoice paid.
 */
class AdminAndroidRuntimeController extends Controller
{
    public function __construct(
        private readonly AndroidRuntimeSummaryService $summary,
        private readonly DeviceRevocationService $revocation,
        private readonly AndroidRuntimeGovernanceAuditService $governance,
    ) {}

    public function devices(Request $request): JsonResponse
    {
        $tenantId = $request->filled('tenant_id') ? (int) $request->input('tenant_id') : null;
        $query = TenantDeviceActivation::query()->orderByDesc('id');
        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }
        if ($request->filled('status')) {
            $query->where('activation_status', (string) $request->input('status'));
        }

        $limit = max(1, min((int) $request->input('limit', 50), 100));

        return response()->json([
            'data' => $query->limit($limit)->get()->map(fn (TenantDeviceActivation $a) => $a->toSafeArray())->all(),
            'summary' => $this->summary->deviceSummary($tenantId),
        ]);
    }

    public function deviceShow(TenantDeviceActivation $activation): JsonResponse
    {
        return response()->json(['data' => $activation->toSafeArray()]);
    }

    public function revoke(Request $request, TenantDeviceActivation $activation): JsonResponse
    {
        $activation = $this->revocation->revoke(
            $activation,
            $request->user(),
            $request->input('reason'),
        );

        return response()->json([
            'data' => $activation->toSafeArray(),
            'meta' => ['revoked' => true],
        ], Response::HTTP_OK);
    }

    public function syncBatches(Request $request): JsonResponse
    {
        $tenantId = $request->filled('tenant_id') ? (int) $request->input('tenant_id') : null;
        $query = TenantAndroidSyncBatch::query()->orderByDesc('id');
        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $limit = max(1, min((int) $request->input('limit', 50), 100));

        return response()->json([
            'data' => $query->limit($limit)->get()->map(fn (TenantAndroidSyncBatch $b) => $b->toSafeArray())->all(),
            'summary' => $this->summary->syncSummary($tenantId),
        ]);
    }

    public function syncBatchShow(TenantAndroidSyncBatch $batch): JsonResponse
    {
        $batch->load('items');

        return response()->json([
            'data' => array_merge($batch->toSafeArray(), [
                'items' => $batch->items->map->toSafeArray()->all(),
            ]),
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $tenantId = $request->filled('tenant_id') ? (int) $request->input('tenant_id') : null;

        return response()->json([
            'data' => $this->summary->recentConflicts($tenantId),
        ]);
    }

    public function governance(): JsonResponse
    {
        return response()->json([
            'data' => $this->governance->evaluate(),
        ]);
    }
}
