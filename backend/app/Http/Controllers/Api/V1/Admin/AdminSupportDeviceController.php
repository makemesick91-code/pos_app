<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportOps\SupportDeviceReactivateRequest;
use App\Http\Requests\Api\SupportOps\SupportDeviceRevokeRequest;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Services\SupportOperations\SupportDeviceOperationsService;
use App\Services\SupportOperations\SupportException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 35 — platform-admin device revoke/reactivate support flow
 * (SUP-R012/R013). Revoke delegates to the Sprint 34 DeviceRevocationService;
 * reactivation is disabled by default and returns a governed not-supported
 * response. Both require a reason code and are audited.
 */
class AdminSupportDeviceController extends Controller
{
    public function __construct(private readonly SupportDeviceOperationsService $devices) {}

    public function revoke(SupportDeviceRevokeRequest $request, Tenant $tenant, TenantDeviceActivation $activation): JsonResponse
    {
        $this->assertBelongs($tenant, $activation);

        $result = $this->devices->revoke($activation, $request->user(), $request->input('reason_code'));

        return response()->json([
            'data' => $result->toSafeArray(),
            'meta' => ['revoked' => true],
        ]);
    }

    public function reactivate(SupportDeviceReactivateRequest $request, Tenant $tenant, TenantDeviceActivation $activation): JsonResponse
    {
        $this->assertBelongs($tenant, $activation);

        try {
            $this->devices->reactivate($activation, $request->user(), $request->input('reason_code'));
        } catch (SupportException $e) {
            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                'meta' => ['reactivated' => false, 'supported' => false],
            ], $e->httpStatus);
        }

        // Unreachable while reactivation is disabled; kept for a future governed path.
        return response()->json(['data' => $activation->fresh()->toSafeArray()]);
    }

    private function assertBelongs(Tenant $tenant, TenantDeviceActivation $activation): void
    {
        abort_if($activation->tenant_id !== $tenant->id, Response::HTTP_NOT_FOUND);
    }
}
