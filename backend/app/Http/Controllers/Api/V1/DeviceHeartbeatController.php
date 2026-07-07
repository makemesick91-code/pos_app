<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceHeartbeatRequest;
use App\Http\Resources\Api\V1\RegisteredDeviceResource;
use App\Services\Subscriptions\DeviceRegistrationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/devices/heartbeat (Sprint 10). Refreshes last_seen_at for a
 * tenant-owned, non-revoked device. A revoked or unknown device is rejected by
 * the service with a stable code.
 */
class DeviceHeartbeatController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DeviceRegistrationService $devices,
    ) {}

    public function store(DeviceHeartbeatRequest $request): JsonResponse
    {
        $device = $this->devices->heartbeat(
            tenant: $this->context->tenant(),
            deviceUuid: (string) $request->input('device_uuid'),
            appVersion: $request->input('app_version'),
        );

        return RegisteredDeviceResource::make($device)
            ->additional([
                'meta' => [
                    'tenant_id' => (int) $this->context->tenantId(),
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                ],
            ])
            ->response();
    }
}
