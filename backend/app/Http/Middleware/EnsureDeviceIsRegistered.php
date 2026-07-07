<?php

namespace App\Http\Middleware;

use App\Models\RegisteredDevice;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an ACTIVE, tenant-owned device for protected Android business APIs
 * (Sprint 10). The device is identified by the `X-Device-UUID` header, which the
 * Android client sends on authenticated calls (see DeviceHeaderInterceptor).
 *
 * Runs after tenant.context. A missing/invalid/revoked device yields 403 with a
 * stable code. saas_admin requests (no tenant) pass through. Device
 * registration and subscription status routes are NOT wrapped by this middleware
 * (a device cannot register itself if it must already be registered).
 */
class EnsureDeviceIsRegistered
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        // Platform admins operate without tenant/device context.
        if ($tenant === null) {
            return $next($request);
        }

        $deviceUuid = trim((string) $request->header('X-Device-UUID'));

        if ($deviceUuid === '') {
            return response()->json([
                'message' => 'Device not registered',
                'code' => 'DEVICE_NOT_REGISTERED',
            ], Response::HTTP_FORBIDDEN);
        }

        $device = RegisteredDevice::query()
            ->forTenant($tenant->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($device === null) {
            return response()->json([
                'message' => 'Device not registered',
                'code' => 'DEVICE_NOT_REGISTERED',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $device->isActive()) {
            return response()->json([
                'message' => 'Device has been revoked',
                'code' => 'DEVICE_REVOKED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
