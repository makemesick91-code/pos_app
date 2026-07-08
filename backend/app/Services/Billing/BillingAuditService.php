<?php

namespace App\Services\Billing;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;

/**
 * Sprint 30 — records every billing/payment mutation to the admin audit trail
 * (BIL-R008). Metadata is redacted by the underlying AdminAuditLogger, so the
 * billing trail can never leak secrets.
 *
 * Controller mutations always carry a User actor. CLI generation (`billing:
 * invoice-generate --apply`) has no authenticated user; it resolves a platform
 * admin as the system actor so an auditable row is still written. If the fresh
 * database has no platform admin at all, the mutation still succeeds and the
 * absence of an actor is surfaced (never a crash).
 */
class BillingAuditService
{
    public function __construct(
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?int $tenantId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): ?AdminAuditLog {
        $actor ??= User::query()->where('is_platform_admin', true)->orderBy('id')->first();

        if (! $actor instanceof User) {
            return null;
        }

        return $this->auditLogger->log(
            actor: $actor,
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            tenantId: $tenantId,
            before: $before,
            after: $after,
            metadata: $metadata,
            request: $request,
        );
    }
}
