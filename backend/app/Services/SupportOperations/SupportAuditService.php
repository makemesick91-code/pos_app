<?php

namespace App\Services\SupportOperations;

use App\Models\TenantSupportAction;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Support\Carbon;

/**
 * Sprint 35 — the audit sink for support operations (SUP-R006/R024/R026).
 *
 * Writes an app-specific `tenant_support_actions` ledger row for every support
 * action (allowed, denied, completed, failed) AND, for a mutation, also writes a
 * redacted entry to the shared `admin_audit_logs` via AdminAuditLogger so the
 * platform-admin action is auditable in the same place as every other admin
 * mutation. Every metadata payload is redacted before it is persisted.
 */
class SupportAuditService
{
    public function __construct(
        private readonly AdminAuditLogger $adminAudit,
        private readonly SupportRedactor $redactor,
    ) {}

    /**
     * Validate that a reason code is present and enumerable (SUP-R005/R025).
     */
    public function assertReasonCode(?string $reasonCode): string
    {
        $reasonCode = is_string($reasonCode) ? trim($reasonCode) : '';

        if ($reasonCode === '') {
            throw SupportException::reasonRequired();
        }

        $allowed = (array) config('support_operations_governance.reason_codes', []);
        if (! in_array($reasonCode, $allowed, true)) {
            throw SupportException::invalidReasonCode();
        }

        return $reasonCode;
    }

    /**
     * Record a support action in the app-specific ledger. Also mirrors a mutation
     * to admin_audit_logs. `metadata` is always redacted.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        int $tenantId,
        string $actionKey,
        string $actionType,
        string $status,
        ?string $reasonCode = null,
        ?string $relatedSubjectType = null,
        ?int $relatedSubjectId = null,
        ?int $supportSessionId = null,
        array $metadata = [],
        bool $mirrorToAdminAudit = true,
    ): TenantSupportAction {
        $redacted = $this->redactor->redact($metadata);

        $action = TenantSupportAction::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor->id,
            'action_key' => $actionKey,
            'action_type' => $actionType,
            'status' => $status,
            'reason_code' => $reasonCode,
            'related_subject_type' => $relatedSubjectType,
            'related_subject_id' => $relatedSubjectId,
            'support_session_id' => $supportSessionId,
            'metadata_json' => $redacted,
            'created_at' => Carbon::now(),
        ]);

        if ($mirrorToAdminAudit) {
            $this->adminAudit->log(
                actor: $actor,
                action: 'SUPPORT_'.strtoupper($actionType),
                targetType: $relatedSubjectType ?? TenantSupportAction::class,
                targetId: $relatedSubjectId ?? $action->id,
                tenantId: $tenantId,
                metadata: array_merge($redacted, [
                    'support_action_key' => $actionKey,
                    'support_status' => $status,
                    'reason_code' => $reasonCode,
                ]),
            );
        }

        return $action;
    }
}
