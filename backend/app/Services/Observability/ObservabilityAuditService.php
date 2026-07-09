<?php

namespace App\Services\Observability;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;

/**
 * Sprint 36 — the audit sink for observability mutations (OBS-R028).
 *
 * Every platform-admin diagnostic action (anomaly acknowledge/resolve/ignore,
 * alert-suggestion dismiss/accept, governed job-retry attempt) is written to the
 * shared `admin_audit_logs` via AdminAuditLogger with redacted metadata. Reads
 * are never audited. Nothing here mutates a domain state.
 */
class ObservabilityAuditService
{
    public function __construct(
        private readonly AdminAuditLogger $adminAudit,
        private readonly ObservabilityRedactor $redactor,
    ) {}

    /**
     * Validate that a reason code is present and enumerable (OBS-R005).
     */
    public function assertReasonCode(?string $reasonCode): string
    {
        $reasonCode = is_string($reasonCode) ? trim($reasonCode) : '';

        if ($reasonCode === '') {
            throw ObservabilityException::reasonRequired();
        }

        $allowed = (array) config('observability_governance.reason_codes', []);
        if (! in_array($reasonCode, $allowed, true)) {
            throw ObservabilityException::invalidReasonCode();
        }

        return $reasonCode;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?int $tenantId = null,
        ?string $reasonCode = null,
        array $metadata = [],
    ): void {
        $redacted = $this->redactor->redact($metadata);

        $this->adminAudit->log(
            actor: $actor,
            action: 'OBSERVABILITY_'.strtoupper($action),
            targetType: $targetType,
            targetId: $targetId,
            tenantId: $tenantId,
            metadata: array_merge($redacted, ['reason_code' => $reasonCode]),
        );
    }
}
