<?php

namespace App\Services\TenantLifecycle;

use App\Models\Tenant;
use App\Models\TenantManualSuspension;
use App\Services\Subscriptions\SubscriptionStatusService;

/**
 * Sprint 25 — the single server-side source of truth for a tenant's lifecycle
 * status and operational access decision (TLS-R001).
 *
 * Precedence (TLS-R004):
 *   1. An ACTIVE manual suspension always wins → suspended / blocked. Neither
 *      subscription renewal nor dunning automation can override it; only an
 *      explicit platform-admin lift clears it.
 *   2. Otherwise the persisted tenant.status is mapped (suspended/inactive →
 *      blocked lifecycle status).
 *   3. Otherwise the subscription state refines active → grace/past_due for
 *      awareness. Monetary blocking stays with the subscription middleware; the
 *      lifecycle guard only denies the blocked lifecycle set.
 *
 * The decision is always recomputed here and never trusted from the client.
 */
class TenantLifecycleService
{
    public function __construct(
        private readonly SubscriptionStatusService $subscriptionStatus,
    ) {}

    public function resolve(Tenant $tenant): TenantLifecycleDecision
    {
        $manual = $tenant->activeManualSuspension();

        if ($manual instanceof TenantManualSuspension) {
            return new TenantLifecycleDecision(
                status: TenantLifecycleStatus::SUSPENDED,
                allowed: false,
                code: 'TENANT_SUSPENDED',
                reason: 'Tenant access is suspended.',
                source: TenantLifecycleDecision::SOURCE_MANUAL_SUSPENSION,
                manuallySuspended: true,
                manualSuspensionId: $manual->id,
            );
        }

        // Legacy persisted tenant status precedes subscription refinement.
        if ($tenant->status === Tenant::STATUS_SUSPENDED) {
            return new TenantLifecycleDecision(
                status: TenantLifecycleStatus::SUSPENDED,
                allowed: false,
                code: 'TENANT_SUSPENDED',
                reason: 'Tenant access is suspended.',
                source: TenantLifecycleDecision::SOURCE_TENANT_STATUS,
                manuallySuspended: false,
            );
        }

        if ($tenant->status === Tenant::STATUS_INACTIVE) {
            return new TenantLifecycleDecision(
                status: TenantLifecycleStatus::ARCHIVED,
                allowed: false,
                code: 'TENANT_ARCHIVED',
                reason: 'Tenant is archived.',
                source: TenantLifecycleDecision::SOURCE_TENANT_STATUS,
                manuallySuspended: false,
            );
        }

        // Active tenant — refine to a non-blocking lifecycle status from the
        // authoritative subscription decision (awareness only).
        $status = $this->deriveActiveStatus($tenant);

        return new TenantLifecycleDecision(
            status: $status,
            allowed: true,
            code: null,
            reason: null,
            source: TenantLifecycleDecision::SOURCE_SUBSCRIPTION,
            manuallySuspended: false,
        );
    }

    private function deriveActiveStatus(Tenant $tenant): string
    {
        $subscription = $this->subscriptionStatus->resolve($tenant);

        return match ($subscription->status) {
            'grace' => TenantLifecycleStatus::GRACE,
            'trial' => TenantLifecycleStatus::ACTIVE,
            'expired', 'cancelled', 'suspended' => TenantLifecycleStatus::PAST_DUE,
            default => TenantLifecycleStatus::ACTIVE,
        };
    }
}
