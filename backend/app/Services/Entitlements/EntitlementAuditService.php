<?php

namespace App\Services\Entitlements;

use App\Models\Tenant;
use App\Models\TenantEntitlementDecision;
use App\Models\User;

/**
 * Sprint 32 — persists denied / degraded / read_only / bypassed runtime
 * entitlement decisions to the audit trail (ENT-R018). Routine allowed reads are
 * NOT persisted (config entitlement_governance.persist_decisions) to avoid DB
 * spam. Metadata is redacted by EntitlementRedactor before persistence, so the
 * trail can never leak secrets or PII (ENT-R020).
 */
class EntitlementAuditService
{
    public function __construct(
        private readonly EntitlementRedactor $redactor,
    ) {}

    /**
     * Record a decision if governance says its status should be persisted.
     * Returns the row, or null when the decision is not persisted.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Tenant $tenant,
        EntitlementDecision $decision,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ): ?TenantEntitlementDecision {
        if (! $decision->shouldPersist()) {
            return null;
        }

        return TenantEntitlementDecision::query()->create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => $actor?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'entitlement_key' => $decision->entitlementKey,
            'resource_type' => $decision->resourceType,
            'action' => $decision->action,
            'decision' => $decision->status,
            'reason_code' => $decision->reasonCode,
            'plan_code' => $decision->planCode,
            'current_usage' => $decision->currentUsage,
            'limit_value' => $decision->limitValue,
            'billing_state' => $decision->billingState,
            'subscription_state' => $decision->subscriptionState,
            'metadata_json' => $this->redactor->redact(array_merge($decision->metadata, $metadata)) ?: null,
        ]);
    }

    /**
     * A safe, redacted summary of recent decisions grouped by decision/reason.
     *
     * @return array<string, mixed>
     */
    public function summary(?int $tenantId = null): array
    {
        $query = TenantEntitlementDecision::query();
        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        $rows = $query->get(['decision', 'reason_code']);

        $byDecision = [];
        $byReason = [];
        foreach ($rows as $row) {
            $byDecision[$row->decision] = ($byDecision[$row->decision] ?? 0) + 1;
            $byReason[$row->reason_code] = ($byReason[$row->reason_code] ?? 0) + 1;
        }

        return [
            'total' => $rows->count(),
            'by_decision' => $byDecision,
            'by_reason_code' => $byReason,
        ];
    }
}
