<?php

namespace App\Services\Subscriptions;

use App\Exceptions\DeviceRegistrationException;
use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backend-authoritative device registration + lifecycle (Sprint 10).
 *
 * - Registration requires an allowed subscription and respects the plan's
 *   max_devices cap. Revoked devices never count toward the cap.
 * - Tenant context is always the authenticated user's own tenant; a client can
 *   never register/heartbeat/revoke against another tenant.
 * - Re-registering an already-active (tenant, device_uuid) row updates its
 *   metadata and returns it WITHOUT consuming a second slot.
 *
 * See Sprint 10 evidence.
 */
class DeviceRegistrationService
{
    public function __construct(
        private readonly SubscriptionStatusService $subscriptionStatus,
    ) {}

    /**
     * Register (or replay) the current device for the tenant/user.
     *
     * @return array{device: RegisteredDevice, existing: bool}
     */
    public function register(
        Tenant $tenant,
        User $user,
        string $deviceUuid,
        ?string $deviceName,
        string $platform,
        ?string $appVersion,
        ?int $storeId,
    ): array {
        $status = $this->subscriptionStatus->resolve($tenant);

        if (! $status->allowed) {
            throw DeviceRegistrationException::subscriptionInactive($status->status, $status->reason);
        }

        return DB::transaction(function () use ($tenant, $user, $deviceUuid, $deviceName, $platform, $appVersion, $storeId, $status) {
            $existing = RegisteredDevice::query()
                ->forTenant($tenant->id)
                ->where('device_uuid', $deviceUuid)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status === RegisteredDevice::STATUS_REVOKED
                    || $existing->status === RegisteredDevice::STATUS_BLOCKED) {
                    throw DeviceRegistrationException::revoked();
                }

                // Active replay: refresh metadata, do NOT consume another slot.
                $existing->fill([
                    'user_id' => $user->id,
                    'store_id' => $storeId ?? $existing->store_id,
                    'device_name' => $deviceName ?? $existing->device_name,
                    'platform' => $platform,
                    'app_version' => $appVersion ?? $existing->app_version,
                    'last_seen_at' => Carbon::now(),
                ]);
                $existing->save();

                return ['device' => $existing, 'existing' => true];
            }

            $activeCount = $tenant->registeredDevices()
                ->where('status', RegisteredDevice::STATUS_ACTIVE)
                ->count();

            $maxDevices = (int) ($status->plan?->max_devices ?? 1);

            if ($activeCount >= $maxDevices) {
                throw DeviceRegistrationException::limitReached($maxDevices, $activeCount);
            }

            $device = RegisteredDevice::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'store_id' => $storeId,
                'device_uuid' => $deviceUuid,
                'device_name' => $deviceName,
                'platform' => $platform,
                'app_version' => $appVersion,
                'last_seen_at' => Carbon::now(),
                'registered_at' => Carbon::now(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
            ]);

            return ['device' => $device, 'existing' => false];
        });
    }

    /**
     * Update last_seen_at for a tenant-owned, non-revoked device.
     */
    public function heartbeat(Tenant $tenant, string $deviceUuid, ?string $appVersion): RegisteredDevice
    {
        $device = RegisteredDevice::query()
            ->forTenant($tenant->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($device === null) {
            throw new DeviceRegistrationException('Device not registered', 'DEVICE_NOT_REGISTERED');
        }

        if (! $device->isActive()) {
            throw DeviceRegistrationException::revoked();
        }

        $device->forceFill([
            'last_seen_at' => Carbon::now(),
            'app_version' => $appVersion ?? $device->app_version,
        ])->save();

        return $device;
    }

    /**
     * Revoke a tenant-owned device. Revoked devices free their slot and lose
     * access to protected business APIs.
     */
    public function revoke(RegisteredDevice $device): RegisteredDevice
    {
        if ($device->status !== RegisteredDevice::STATUS_REVOKED) {
            $device->forceFill([
                'status' => RegisteredDevice::STATUS_REVOKED,
                'revoked_at' => Carbon::now(),
            ])->save();
        }

        return $device;
    }
}
