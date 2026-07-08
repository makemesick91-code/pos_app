<?php

namespace App\Services\SupportOperations;

use App\Models\Tenant;
use App\Models\TenantSupportAction;
use App\Models\TenantSupportSession;
use App\Models\User;

/**
 * Sprint 35 — support impersonation (SUP-R018/R019).
 *
 * Impersonation is DISABLED by default in this codebase. Borrowing a tenant
 * user's identity is not required for any safe support-visibility need — the
 * read-only context (SupportReadOnlyContextService) covers all of them without
 * ever assuming a tenant identity or exposing a credential.
 *
 * When disabled, start() records a governed DENIED support action (with a denied
 * session row for the audit trail) and throws a safe SupportException. It never
 * returns or persists a raw credential/token. Should a governed, read-only-safe,
 * time-bound implementation ever be introduced, it must remain platform.admin
 * only, audited, and must never expose raw credentials.
 */
class SupportImpersonationService
{
    public function __construct(private readonly SupportAuditService $audit) {}

    public function isEnabled(): bool
    {
        return (bool) config('support_operations_governance.impersonation.enabled', false);
    }

    /**
     * @return never
     */
    public function start(Tenant $tenant, User $actor, ?string $reasonCode)
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $message = (string) config(
            'support_operations_governance.impersonation.disabled_reason',
            'Impersonation is disabled by governance; use a read-only support context instead.',
        );

        // Record a denied session + support action for the audit trail. No raw
        // credential is ever produced.
        $session = TenantSupportSession::query()->create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => $actor->id,
            'session_type' => TenantSupportSession::TYPE_IMPERSONATION,
            'status' => TenantSupportSession::STATUS_DENIED,
            'reason_code' => $reasonCode,
            'starts_at' => now(),
            'expires_at' => now(),
            'scope_json' => ['read_only' => true, 'denied' => true],
            'metadata_json' => ['reason' => 'impersonation_disabled'],
        ]);

        $this->audit->record(
            actor: $actor,
            tenantId: $tenant->id,
            actionKey: 'impersonation.start',
            actionType: TenantSupportAction::TYPE_IMPERSONATION_DENIED,
            status: TenantSupportAction::STATUS_DENIED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantSupportSession::class,
            relatedSubjectId: $session->id,
            supportSessionId: $session->id,
            metadata: ['enabled' => $this->isEnabled()],
        );

        throw SupportException::impersonationDisabled($message);
    }
}
