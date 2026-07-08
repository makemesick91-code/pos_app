<?php

namespace App\Services\SupportOperations;

use App\Models\Tenant;
use App\Models\TenantSupportAction;
use App\Models\TenantSupportSession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 35 — a time-bound, tenant-scoped, read-only support context (SUP-R017).
 *
 * Opening a context records that a platform admin is reading a specific tenant's
 * data for a bounded window. It grants NO mutation power (it is purely an audit +
 * scoping record) and holds NO raw credentials/tokens. Start/end/denied are
 * audited. Expiry is enforced by query/service check (no background job).
 */
class SupportReadOnlyContextService
{
    public function __construct(private readonly SupportAuditService $audit) {}

    public function start(Tenant $tenant, User $actor, ?string $reasonCode, ?int $ttlMinutes = null): TenantSupportSession
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $default = (int) config('support_operations_governance.read_only_context.default_ttl_minutes', 60);
        $max = (int) config('support_operations_governance.read_only_context.max_ttl_minutes', 240);
        $ttl = $ttlMinutes ?? $default;
        $ttl = max(1, min($ttl, $max));

        $now = Carbon::now();
        $session = TenantSupportSession::query()->create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => $actor->id,
            'session_type' => TenantSupportSession::TYPE_READ_ONLY_CONTEXT,
            'status' => TenantSupportSession::STATUS_ACTIVE,
            'reason_code' => $reasonCode,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addMinutes($ttl),
            // scope is deliberately safe: read-only + tenant id, no credentials.
            'scope_json' => ['read_only' => true, 'tenant_id' => $tenant->id],
            'metadata_json' => [],
        ]);

        $this->audit->record(
            actor: $actor,
            tenantId: $tenant->id,
            actionKey: 'read_only_context.start',
            actionType: TenantSupportAction::TYPE_READ_CONTEXT_STARTED,
            status: TenantSupportAction::STATUS_ALLOWED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantSupportSession::class,
            relatedSubjectId: $session->id,
            supportSessionId: $session->id,
            metadata: ['ttl_minutes' => $ttl],
        );

        return $session;
    }

    public function end(TenantSupportSession $session, User $actor): TenantSupportSession
    {
        if ($session->status === TenantSupportSession::STATUS_ACTIVE) {
            $session->forceFill([
                'status' => TenantSupportSession::STATUS_ENDED,
                'ended_at' => Carbon::now(),
            ])->save();
        }

        $this->audit->record(
            actor: $actor,
            tenantId: $session->tenant_id,
            actionKey: 'read_only_context.end',
            actionType: TenantSupportAction::TYPE_READ_CONTEXT_ENDED,
            status: TenantSupportAction::STATUS_COMPLETED,
            reasonCode: $session->reason_code,
            relatedSubjectType: TenantSupportSession::class,
            relatedSubjectId: $session->id,
            supportSessionId: $session->id,
        );

        return $session->refresh();
    }

    /**
     * Assert a session is still effective, expiring it lazily if past its window.
     */
    public function assertEffective(TenantSupportSession $session): void
    {
        if ($session->status === TenantSupportSession::STATUS_ACTIVE && $session->isExpired()) {
            $session->forceFill(['status' => TenantSupportSession::STATUS_EXPIRED])->save();
        }

        if (! $session->isEffective()) {
            throw SupportException::sessionExpired();
        }
    }
}
