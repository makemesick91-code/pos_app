<?php

namespace App\Http\Controllers\Api\V1\Android;

use App\Http\Controllers\Controller;
use App\Models\RegisteredDevice;
use App\Services\AndroidRuntime\CashierRuntimeSessionService;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 34 — cashier runtime session validation (ADR-R010/R011). There is no
 * server-side session token minted here (auth is Sanctum); this validates and
 * returns the cashier's runtime posture (allowed / read_only / blocked) for the
 * paired tenant/branch/register/device. A denied attempt is audit-logged.
 */
class CashierRuntimeSessionController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CashierRuntimeSessionService $sessions,
        private readonly DeviceActivationService $activation,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    public function start(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    public function end(Request $request): JsonResponse
    {
        // Ending a session is a client-side clear; the server simply confirms.
        return response()->json([
            'data' => ['ended' => true],
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }

    private function respond(Request $request): JsonResponse
    {
        $tenant = $this->context->tenant();
        $cashier = $this->context->user();

        $device = $this->deviceFor($request);
        $activation = $device !== null ? $this->activation->resolveForDevice($device) : null;

        $decision = $this->sessions->check(
            $tenant,
            $cashier,
            $activation,
            $this->context->storeId(),
            $request->filled('register_id') ? (int) $request->input('register_id') : null,
        );

        return response()->json([
            'data' => $this->sessions->summary($tenant, $cashier, $decision),
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ], $decision->allowed ? 200 : $decision->httpStatus);
    }

    private function deviceFor(Request $request): ?RegisteredDevice
    {
        $deviceUuid = trim((string) $request->header('X-Device-UUID'));

        if ($deviceUuid === '') {
            return null;
        }

        return RegisteredDevice::query()
            ->forTenant((int) $this->context->tenantId())
            ->where('device_uuid', $deviceUuid)
            ->first();
    }
}
