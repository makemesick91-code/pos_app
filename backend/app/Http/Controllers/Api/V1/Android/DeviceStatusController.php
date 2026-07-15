<?php

namespace App\Http\Controllers\Api\V1\Android;

use App\Http\Controllers\Controller;
use App\Models\RegisteredDevice;
use App\Models\TenantDeviceActivation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-8C-07 — the server-authoritative device-status poll (UIX8C-R221).
 *
 * This endpoint is deliberately reachable by a REVOKED device (it is not behind
 * `device.registered`), so the Android startup state machine can learn that a
 * device is revoked AND why, instead of a reasonless 403 from the heartbeat
 * (UIX8C-R220/R234). It is READ-ONLY presentation over the canonical
 * activation/registration state — it never mutates, never re-issues, and never
 * returns a token, fingerprint hash, or installation hash (UIX8C-R227/R246).
 *
 * The tenant is server-resolved from the authenticated context; a client-supplied
 * tenant id is never trusted (UIX8C-R223).
 */
class DeviceStatusController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = $this->context->tenant();
        $deviceUuid = trim((string) $request->header('X-Device-UUID'));

        $device = $deviceUuid === '' ? null : RegisteredDevice::query()
            ->forTenant($tenant->id)
            ->where('device_uuid', $deviceUuid)
            ->with('store')
            ->first();

        if (! $device instanceof RegisteredDevice) {
            return response()->json([
                'data' => [
                    'status' => 'not_activated',
                    'active' => false,
                    'revoked' => false,
                    'revocation_reason' => null,
                    'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
                    'outlet' => null,
                    'device_name' => null,
                    'app_version' => null,
                    'activated_at' => null,
                    'last_seen_at' => null,
                ],
                'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
            ], Response::HTTP_OK);
        }

        $activation = TenantDeviceActivation::query()
            ->forTenant($tenant->id)
            ->where('device_id', $device->id)
            ->orderByDesc('id')
            ->first();

        [$status, $revoked, $reason] = $this->posture($device, $activation);

        return response()->json([
            'data' => [
                'status' => $status,
                'active' => $status === 'active',
                'revoked' => $revoked,
                'revocation_reason' => $reason,
                'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
                'outlet' => $device->store
                    ? ['id' => $device->store->id, 'name' => $device->store->name]
                    : null,
                'device_name' => $device->device_name,
                'app_version' => $activation?->app_version,
                'activated_at' => optional($activation?->activated_at)->toIso8601String(),
                'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
            ],
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ], Response::HTTP_OK);
    }

    /**
     * Presentation-only posture derivation from canonical device/activation state.
     * A revoked device (registration OR activation) is always reported revoked,
     * fail-closed — the client never treats "unknown" as active.
     *
     * @return array{0:string,1:bool,2:?string}
     */
    private function posture(RegisteredDevice $device, ?TenantDeviceActivation $activation): array
    {
        $deviceRevoked = ! $device->isActive();
        $activationRevoked = $activation !== null && $activation->isRevoked();

        if ($deviceRevoked || $activationRevoked) {
            $reason = $activation?->revocation_reason
                ?: 'Perangkat ini telah dinonaktifkan oleh admin.';

            return ['revoked', true, $reason];
        }

        if ($activation !== null && $activation->isExpired()) {
            return ['expired', false, 'Aktivasi perangkat telah kedaluwarsa.'];
        }

        if ($activation !== null && $activation->isUsable()) {
            return ['active', false, null];
        }

        return ['inactive', false, null];
    }
}
