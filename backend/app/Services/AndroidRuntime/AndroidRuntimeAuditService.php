<?php

namespace App\Services\AndroidRuntime;

use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;

/**
 * Sprint 34 — the audit sink for Android runtime actions (ADR-R011/R022/R026/R027).
 *
 * Android-specific denied/blocked runtime decisions are the audit trail of the
 * sync batch/item rows (conflict_code + redacted failure_reason) and the device
 * activation `failure_reason`; this service additionally writes a redacted
 * admin_audit_logs entry when a platform admin performs a mutating support action
 * (device revoke / explicit bypass) so the platform-admin action is auditable
 * (ADR-R028). Every metadata payload is redacted before it is persisted.
 */
class AndroidRuntimeAuditService
{
    public const ACTION_DEVICE_ACTIVATED = 'ANDROID_DEVICE_ACTIVATED';
    public const ACTION_DEVICE_REVOKED = 'ANDROID_DEVICE_REVOKED';
    public const ACTION_DEVICE_ACTIVATION_DENIED = 'ANDROID_DEVICE_ACTIVATION_DENIED';
    public const ACTION_SYNC_REJECTED = 'ANDROID_SYNC_REJECTED';
    public const ACTION_CASHIER_DENIED = 'ANDROID_CASHIER_DENIED';
    public const ACTION_SUPPORT_BYPASS = 'ANDROID_SUPPORT_BYPASS';

    public function __construct(
        private readonly AdminAuditLogger $adminAudit,
        private readonly AndroidSyncRedactor $redactor,
    ) {}

    /**
     * Record a platform-admin mutating action against a device activation.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordAdminAction(
        User $actor,
        string $action,
        TenantDeviceActivation $activation,
        array $metadata = [],
    ): void {
        $this->adminAudit->log(
            actor: $actor,
            action: $action,
            targetType: TenantDeviceActivation::class,
            targetId: $activation->id,
            tenantId: $activation->tenant_id,
            metadata: $this->redactor->redact($metadata),
        );
    }

    /**
     * Record an explicit platform-admin / device-support bypass (ADR-R028).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordBypass(User $actor, int $tenantId, string $context, array $metadata = []): void
    {
        $this->adminAudit->log(
            actor: $actor,
            action: self::ACTION_SUPPORT_BYPASS,
            targetType: 'android_runtime',
            targetId: null,
            tenantId: $tenantId,
            metadata: $this->redactor->redact(array_merge(['context' => $context], $metadata)),
        );
    }
}
