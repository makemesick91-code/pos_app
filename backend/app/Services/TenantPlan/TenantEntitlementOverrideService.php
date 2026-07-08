<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 26 — creates a limited per-tenant feature entitlement override on behalf
 * of a platform admin (TPE-R006). Reason is mandatory and sanitized; every
 * mutation is audit-logged with redacted metadata (TPE-R007).
 *
 * An override refines the plan grant up or down for a single feature but is NEVER
 * a lifecycle bypass: tenant lifecycle enforcement runs first, so an override can
 * never re-enable a suspended/cancelled/archived tenant (TPE-R004/R005). This
 * service is the only writer of tenant_entitlement_overrides.
 */
class TenantEntitlementOverrideService
{
    use SanitizesTenantPlanText;

    public function __construct(
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function set(
        Tenant $tenant,
        User $actor,
        string $entitlementKey,
        bool $enabled,
        string $reason,
        ?string $reasonCategory = null,
        ?string $effectiveUntil = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): TenantEntitlementOverride {
        $cleanReason = $this->sanitizeReason($reason) ?? 'Entitlement override.';
        $cleanMetadata = $this->sanitizeMetadata($metadata);

        $override = DB::transaction(function () use ($tenant, $actor, $entitlementKey, $enabled, $cleanReason, $reasonCategory, $effectiveUntil, $cleanMetadata) {
            // Revoke prior active overrides for the same feature so the newest
            // decision is unambiguous.
            TenantEntitlementOverride::query()
                ->where('tenant_id', $tenant->id)
                ->where('entitlement_key', $entitlementKey)
                ->where('status', TenantEntitlementOverride::STATUS_ACTIVE)
                ->update(['status' => TenantEntitlementOverride::STATUS_REVOKED]);

            return TenantEntitlementOverride::query()->create([
                'tenant_id' => $tenant->id,
                'entitlement_key' => $entitlementKey,
                'enabled' => $enabled,
                'status' => TenantEntitlementOverride::STATUS_ACTIVE,
                'reason' => $cleanReason,
                'reason_category' => $reasonCategory,
                'effective_from' => now(),
                'effective_until' => $effectiveUntil ? now()->parse($effectiveUntil) : null,
                'actor_user_id' => $actor->id,
                'metadata' => $cleanMetadata,
            ]);
        });

        $this->audit->log(
            actor: $actor,
            action: 'tenant.entitlement_override',
            targetType: Tenant::class,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            before: ['entitlement' => $entitlementKey],
            after: ['entitlement' => $entitlementKey, 'enabled' => $enabled],
            metadata: ['reason_category' => $reasonCategory, 'override_id' => $override->id],
            request: $request,
        );

        return $override;
    }
}
