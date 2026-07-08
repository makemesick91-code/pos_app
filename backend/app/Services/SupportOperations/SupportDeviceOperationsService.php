<?php

namespace App\Services\SupportOperations;

use App\Models\TenantDeviceActivation;
use App\Models\TenantSupportAction;
use App\Models\User;
use App\Services\AndroidRuntime\DeviceRevocationService;

/**
 * Sprint 35 — governed device revoke/reactivate support flow (SUP-R012/R013/R014).
 *
 * Revoke ALWAYS delegates to the Sprint 34 DeviceRevocationService (which blocks
 * future sync/write and moves the paired RegisteredDevice to REVOKED); it never
 * touches the DB directly. Reactivation is disabled by default — re-activation
 * must go through the standard Sprint 34 DeviceActivationService flow so the
 * entitlement/device-limit gate is re-run; this service returns a governed
 * not-supported response instead of silently re-enabling a device. Every action
 * requires a reason code and is audited. Manual suspension always wins.
 */
class SupportDeviceOperationsService
{
    public function __construct(
        private readonly DeviceRevocationService $revocation,
        private readonly SupportAuditService $audit,
    ) {}

    /**
     * Revoke a device activation through the Sprint 34 service. Idempotent.
     */
    public function revoke(TenantDeviceActivation $activation, User $actor, ?string $reasonCode): TenantDeviceActivation
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $result = $this->revocation->revoke($activation, $actor, 'support:'.$reasonCode);

        $this->audit->record(
            actor: $actor,
            tenantId: $activation->tenant_id,
            actionKey: 'device.revoke',
            actionType: TenantSupportAction::TYPE_DEVICE_REVOKED,
            status: TenantSupportAction::STATUS_COMPLETED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantDeviceActivation::class,
            relatedSubjectId: $activation->id,
            metadata: ['activation_status' => $result->activation_status],
        );

        return $result;
    }

    /**
     * Reactivation is a governed capability that is disabled by default
     * (SUP-R013). When disabled, the attempt is audited as denied and a safe
     * not-supported SupportException is thrown.
     */
    public function reactivate(TenantDeviceActivation $activation, User $actor, ?string $reasonCode): never
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $enabled = (bool) config('support_operations_governance.device_operations.reactivate_enabled', false);
        $message = (string) config(
            'support_operations_governance.device_operations.reactivate_not_supported_reason',
            'Governed device reactivation is disabled.',
        );

        // Even if a future governed reactivation is enabled, it must never bypass
        // the standard activation gate; this service intentionally does not
        // re-enable a device directly, so it always fails closed here.
        $this->audit->record(
            actor: $actor,
            tenantId: $activation->tenant_id,
            actionKey: 'device.reactivate',
            actionType: TenantSupportAction::TYPE_DEVICE_REACTIVATED,
            status: TenantSupportAction::STATUS_DENIED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantDeviceActivation::class,
            relatedSubjectId: $activation->id,
            metadata: ['reactivate_enabled' => $enabled, 'outcome' => 'not_supported'],
        );

        throw SupportException::reactivationNotSupported($message);
    }
}
