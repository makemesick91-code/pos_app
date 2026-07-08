<?php

namespace App\Http\Controllers\Api\V1\Android;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Android\ActivateDeviceRequest;
use App\Models\RegisteredDevice;
use App\Services\AndroidRuntime\AndroidRuntimeException;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 34 — Android device/register activation + heartbeat (ADR-R002/R003).
 *
 * activate() runs inside the authenticated tenant context but NOT behind
 * device.registered (the device is not registered yet). It never returns the raw
 * activation token. Activation is idempotent and entitlement-gated inside
 * DeviceActivationService.
 */
class DeviceActivationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DeviceActivationService $activation,
    ) {}

    public function activate(ActivateDeviceRequest $request): JsonResponse
    {
        $tenant = $this->context->tenant();

        try {
            $activation = $this->activation->activate(
                tenant: $tenant,
                rawToken: (string) $request->input('activation_token'),
                fingerprint: (string) $request->input('device_fingerprint'),
                deviceUuid: $request->input('device_uuid'),
                label: $request->input('device_label'),
                actor: $this->context->user(),
            );
        } catch (AndroidRuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->reasonCode,
            ], $e->status);
        }

        return response()->json([
            'data' => $activation->toSafeArray(),
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ], Response::HTTP_OK);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $tenant = $this->context->tenant();
        $deviceUuid = trim((string) $request->header('X-Device-UUID'));

        $device = RegisteredDevice::query()
            ->forTenant($tenant->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($device === null) {
            return response()->json(['message' => 'Device not registered', 'code' => 'DEVICE_NOT_REGISTERED'], Response::HTTP_FORBIDDEN);
        }

        $activation = $this->activation->heartbeat($this->activation->resolveForDevice($device));

        return response()->json([
            'data' => $activation->toSafeArray(),
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }
}
