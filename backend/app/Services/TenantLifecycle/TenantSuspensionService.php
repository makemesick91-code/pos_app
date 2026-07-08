<?php

namespace App\Services\TenantLifecycle;

use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 25 — creates and lifts manual tenant suspensions on behalf of a
 * platform admin (TLS-R002).
 *
 * Every mutation is idempotent, audit-logged (TLS-R005), and appended to the
 * tenant lifecycle event trail with a sanitized, mandatory reason (TLS-R006).
 * This service NEVER charges, renews, or touches subscription automation; it is
 * the only writer of tenant_manual_suspensions, which guarantees renewal/dunning
 * automation can never override a manual suspension (TLS-R004).
 */
class TenantSuspensionService
{
    use SanitizesTenantLifecycleText;

    public function __construct(
        private readonly TenantLifecycleService $lifecycle,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{suspension: TenantManualSuspension, already: bool, decision: TenantLifecycleDecision}
     */
    public function suspend(
        Tenant $tenant,
        User $actor,
        string $reason,
        ?string $reasonCategory = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): array {
        $existing = $tenant->activeManualSuspension();

        if ($existing instanceof TenantManualSuspension) {
            // Idempotent — re-suspending an already-suspended tenant is a safe
            // no-op that never corrupts state.
            return [
                'suspension' => $existing,
                'already' => true,
                'decision' => $this->lifecycle->resolve($tenant->refresh()),
            ];
        }

        $cleanReason = $this->sanitizeReason($reason) ?? 'Manual suspension.';
        $cleanMetadata = $this->sanitizeMetadata($metadata);
        $previous = $this->lifecycle->resolve($tenant);

        $suspension = DB::transaction(function () use ($tenant, $actor, $cleanReason, $reasonCategory, $cleanMetadata, $previous) {
            $suspension = TenantManualSuspension::query()->create([
                'tenant_id' => $tenant->id,
                'status' => TenantManualSuspension::STATUS_ACTIVE,
                'reason' => $cleanReason,
                'reason_category' => $reasonCategory,
                'effective_at' => now(),
                'suspended_by_user_id' => $actor->id,
                'metadata' => $cleanMetadata,
            ]);

            TenantLifecycleEvent::query()->create([
                'tenant_id' => $tenant->id,
                'action' => TenantLifecycleEvent::ACTION_MANUAL_SUSPEND,
                'previous_status' => $previous->status,
                'new_status' => TenantLifecycleStatus::SUSPENDED,
                'reason' => $cleanReason,
                'reason_category' => $reasonCategory,
                'effective_at' => now(),
                'actor_user_id' => $actor->id,
                'manual_suspension_id' => $suspension->id,
                'metadata' => $cleanMetadata,
            ]);

            return $suspension;
        });

        $this->audit->log(
            actor: $actor,
            action: 'tenant.manual_suspend',
            targetType: Tenant::class,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            before: ['lifecycle_status' => $previous->status],
            after: ['lifecycle_status' => TenantLifecycleStatus::SUSPENDED],
            metadata: ['reason_category' => $reasonCategory, 'manual_suspension_id' => $suspension->id],
            request: $request,
        );

        return [
            'suspension' => $suspension,
            'already' => false,
            'decision' => $this->lifecycle->resolve($tenant->refresh()),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{suspension: ?TenantManualSuspension, already: bool, decision: TenantLifecycleDecision}
     */
    public function lift(
        Tenant $tenant,
        User $actor,
        string $reason,
        ?array $metadata = null,
        ?Request $request = null,
    ): array {
        $existing = $tenant->activeManualSuspension();

        if (! $existing instanceof TenantManualSuspension) {
            // Idempotent — lifting a tenant that is not manually suspended is a
            // safe no-op with a clear response.
            return [
                'suspension' => null,
                'already' => true,
                'decision' => $this->lifecycle->resolve($tenant->refresh()),
            ];
        }

        $cleanReason = $this->sanitizeReason($reason) ?? 'Manual lift.';
        $cleanMetadata = $this->sanitizeMetadata($metadata);

        DB::transaction(function () use ($tenant, $actor, $existing, $cleanReason, $cleanMetadata) {
            $existing->update([
                'status' => TenantManualSuspension::STATUS_LIFTED,
                'lifted_at' => now(),
                'lift_reason' => $cleanReason,
                'lifted_by_user_id' => $actor->id,
            ]);

            TenantLifecycleEvent::query()->create([
                'tenant_id' => $tenant->id,
                'action' => TenantLifecycleEvent::ACTION_MANUAL_LIFT,
                'previous_status' => TenantLifecycleStatus::SUSPENDED,
                'new_status' => TenantLifecycleStatus::ACTIVE,
                'reason' => $cleanReason,
                'reason_category' => $existing->reason_category,
                'effective_at' => now(),
                'actor_user_id' => $actor->id,
                'manual_suspension_id' => $existing->id,
                'metadata' => $cleanMetadata,
            ]);
        });

        $this->audit->log(
            actor: $actor,
            action: 'tenant.lift_suspension',
            targetType: Tenant::class,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            before: ['lifecycle_status' => TenantLifecycleStatus::SUSPENDED],
            after: ['lifecycle_status' => TenantLifecycleStatus::ACTIVE],
            metadata: ['manual_suspension_id' => $existing->id],
            request: $request,
        );

        return [
            'suspension' => $existing->refresh(),
            'already' => false,
            'decision' => $this->lifecycle->resolve($tenant->refresh()),
        ];
    }
}
