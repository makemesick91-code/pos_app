<?php

namespace App\Services\AndroidRuntime;

use App\Models\RegisteredDevice;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 34 — revoke a device activation (ADR-R026). A revoked activation blocks
 * all future sync/write (AndroidRuntimeAccessService::authorizeSync denies it) and
 * the paired RegisteredDevice is moved to REVOKED so the Sprint 10 device.registered
 * gate also rejects it. Platform-admin only; the mutation is audited (ADR-R028).
 * Idempotent — revoking an already-revoked activation is a no-op success.
 */
class DeviceRevocationService
{
    public function __construct(
        private readonly AndroidRuntimeAuditService $audit,
    ) {}

    public function revoke(TenantDeviceActivation $activation, User $actor, ?string $reason = null): TenantDeviceActivation
    {
        if ($activation->isRevoked()) {
            return $activation;
        }

        DB::transaction(function () use ($activation, $reason) {
            $activation->forceFill([
                'activation_status' => TenantDeviceActivation::STATUS_REVOKED,
                'revoked_at' => Carbon::now(),
                'failure_reason' => $reason !== null ? 'REVOKED: '.$reason : 'REVOKED',
            ])->save();

            if ($activation->device_id !== null) {
                RegisteredDevice::query()
                    ->whereKey($activation->device_id)
                    ->update([
                        'status' => RegisteredDevice::STATUS_REVOKED,
                        'revoked_at' => Carbon::now(),
                    ]);
            }
        });

        $this->audit->recordAdminAction(
            $actor,
            AndroidRuntimeAuditService::ACTION_DEVICE_REVOKED,
            $activation,
            ['reason' => $reason],
        );

        return $activation->refresh();
    }
}
