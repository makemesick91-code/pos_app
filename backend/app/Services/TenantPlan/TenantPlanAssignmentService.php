<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\TenantPlanAssignment;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 26 — assigns a plan to a tenant on behalf of a platform admin
 * (TPE-R006). Assignment is the authoritative writer of tenant_plan_assignments;
 * it supersedes the tenant's previous active assignment (expired) and appends an
 * audit log with redacted metadata (TPE-R007).
 *
 * Assignment NEVER charges, NEVER touches subscription renewal/dunning
 * automation, and NEVER bypasses tenant lifecycle enforcement — a suspended
 * tenant stays suspended after any plan change (TPE-R004/R005).
 */
class TenantPlanAssignmentService
{
    use SanitizesTenantPlanText;

    public function __construct(
        private readonly TenantPlanRegistrar $registrar,
        private readonly TenantPlanResolver $resolver,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function assign(
        Tenant $tenant,
        User $actor,
        string $planKey,
        string $source = TenantPlanAssignment::SOURCE_PLATFORM_ADMIN,
        ?string $reason = null,
        ?string $effectiveFrom = null,
        ?string $effectiveUntil = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): TenantPlanAssignment {
        $this->registrar->ensure();

        $plan = TenantPlan::query()->where('key', $planKey)->firstOrFail();
        $previous = $this->resolver->resolve($tenant);

        $cleanReason = $this->sanitizeReason($reason);
        $cleanMetadata = $this->sanitizeMetadata($metadata);

        $assignment = DB::transaction(function () use ($tenant, $plan, $actor, $source, $cleanReason, $effectiveFrom, $effectiveUntil, $cleanMetadata) {
            // Supersede the tenant's current active assignments.
            TenantPlanAssignment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', TenantPlanAssignment::STATUS_ACTIVE)
                ->update(['status' => TenantPlanAssignment::STATUS_EXPIRED]);

            return TenantPlanAssignment::query()->create([
                'tenant_id' => $tenant->id,
                'tenant_plan_id' => $plan->id,
                'status' => TenantPlanAssignment::STATUS_ACTIVE,
                'effective_from' => $effectiveFrom ? now()->parse($effectiveFrom) : now(),
                'effective_until' => $effectiveUntil ? now()->parse($effectiveUntil) : null,
                'source' => $source,
                'assigned_by_user_id' => $actor->id,
                'reason' => $cleanReason,
                'metadata' => $cleanMetadata,
            ]);
        });

        $this->audit->log(
            actor: $actor,
            action: 'tenant.plan_assign',
            targetType: Tenant::class,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            before: ['plan_key' => $previous->planKey],
            after: ['plan_key' => $plan->key],
            metadata: ['source' => $source, 'assignment_id' => $assignment->id],
            request: $request,
        );

        return $assignment;
    }
}
