<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Sprint 11 — platform-admin device administration.
 *
 * Lists a tenant's devices and revokes them, reusing the Sprint 10
 * RegisteredDevice status model. A revoked device frees its slot and can no
 * longer pass the device.registered business-API gate. Revoke is idempotent and
 * audit-logged.
 */
class AdminDeviceService
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, RegisteredDevice>
     */
    public function listForTenant(Tenant $tenant, ?string $status = null): Collection
    {
        $query = $tenant->registeredDevices()->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function revoke(
        User $actor,
        Tenant $tenant,
        RegisteredDevice $device,
        ?Request $request = null,
    ): RegisteredDevice {
        if ((int) $device->tenant_id !== (int) $tenant->id) {
            throw new RuntimeException('Device does not belong to tenant.');
        }

        $before = $this->snapshot($device);

        // Idempotent: revoking an already-revoked device is a safe no-op.
        if ($device->status !== RegisteredDevice::STATUS_REVOKED) {
            $device->forceFill([
                'status' => RegisteredDevice::STATUS_REVOKED,
                'revoked_at' => Carbon::now(),
            ])->save();

            $this->audit->log(
                actor: $actor,
                action: AdminAuditLog::ACTION_DEVICE_REVOKED,
                targetType: AdminAuditLog::TARGET_DEVICE,
                targetId: $device->id,
                tenantId: $tenant->id,
                before: $before,
                after: $this->snapshot($device),
                request: $request,
            );
        }

        return $device;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(RegisteredDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'status' => $device->status,
            'revoked_at' => optional($device->revoked_at)->toIso8601String(),
        ];
    }
}
