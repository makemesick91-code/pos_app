<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexDeviceRequest;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Resources\Api\V1\RegisteredDeviceResource;
use App\Models\RegisteredDevice;
use App\Services\Subscriptions\DeviceRegistrationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered device management for the authenticated tenant (Sprint 10):
 * register, list, and revoke. Every action is tenant-owned and backend
 * enforced — tenant A can never register/list/revoke tenant B's devices, and
 * the plan's device limit is authoritative. Registration requires an allowed
 * subscription.
 */
class RegisteredDeviceController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DeviceRegistrationService $devices,
    ) {}

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $tenant = $this->context->tenant();

        $result = $this->devices->register(
            tenant: $tenant,
            user: $this->context->user(),
            deviceUuid: (string) $request->input('device_uuid'),
            deviceName: $request->input('device_name'),
            platform: (string) $request->input('platform', RegisteredDevice::PLATFORM_ANDROID),
            appVersion: $request->input('app_version'),
            storeId: $request->filled('store_id') ? (int) $request->input('store_id') : null,
        );

        $status = $result['existing'] ? Response::HTTP_OK : Response::HTTP_CREATED;

        return RegisteredDeviceResource::make($result['device'])
            ->additional([
                'meta' => [
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                    'existing_device' => $result['existing'],
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }

    public function index(IndexDeviceRequest $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->context->tenantId();

        $query = RegisteredDevice::query()
            ->forTenant($tenantId)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        return RegisteredDeviceResource::collection($query->get())->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function revoke(RegisteredDevice $device): JsonResponse
    {
        $this->authorizeTenant($device);

        $this->devices->revoke($device);

        return RegisteredDeviceResource::make($device->refresh())
            ->additional([
                'meta' => [
                    'tenant_id' => (int) $this->context->tenantId(),
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                ],
            ])
            ->response();
    }

    private function authorizeTenant(RegisteredDevice $device): void
    {
        abort_if(
            (int) $device->tenant_id !== (int) $this->context->tenantId(),
            Response::HTTP_NOT_FOUND,
        );
    }
}
