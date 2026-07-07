<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexAdminDeviceRequest;
use App\Http\Resources\Api\V1\Admin\AdminDeviceResource;
use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Services\Admin\AdminDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 11 — admin device list/revoke for a tenant. Platform admin only. A
 * device must belong to the routed tenant; revoke is idempotent and audit
 * logged, and a revoked device can no longer pass the device.registered gate.
 */
class AdminTenantDeviceController extends Controller
{
    public function __construct(
        private readonly AdminDeviceService $devices,
    ) {}

    public function index(IndexAdminDeviceRequest $request, Tenant $tenant): AnonymousResourceCollection
    {
        $devices = $this->devices->listForTenant($tenant, $request->input('status'));

        return AdminDeviceResource::collection($devices)->additional([
            'meta' => [
                'tenant_id' => $tenant->id,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function revoke(Tenant $tenant, RegisteredDevice $device): JsonResponse
    {
        abort_if((int) $device->tenant_id !== (int) $tenant->id, Response::HTTP_NOT_FOUND);

        $device = $this->devices->revoke(
            actor: request()->user(),
            tenant: $tenant,
            device: $device,
            request: request(),
        );

        return AdminDeviceResource::make($device)
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
