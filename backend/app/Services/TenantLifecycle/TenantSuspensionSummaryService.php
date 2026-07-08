<?php

namespace App\Services\TenantLifecycle;

use App\Models\TenantLifecycleEvent;
use App\Models\TenantManualSuspension;

/**
 * Sprint 25 — read-only, secret-safe manual suspension governance summary.
 *
 * Aggregates counts of active/lifted manual suspensions, suspensions by reason
 * category, and recent lifecycle event actions. Used by the admin summary API
 * and the tenant-lifecycle:suspension-summary command. Never exposes reasons
 * verbatim or any secret.
 */
class TenantSuspensionSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $active = TenantManualSuspension::query()
            ->where('status', TenantManualSuspension::STATUS_ACTIVE)
            ->count();

        $lifted = TenantManualSuspension::query()
            ->where('status', TenantManualSuspension::STATUS_LIFTED)
            ->count();

        $byCategory = TenantManualSuspension::query()
            ->where('status', TenantManualSuspension::STATUS_ACTIVE)
            ->selectRaw('reason_category, COUNT(*) as total')
            ->groupBy('reason_category')
            ->pluck('total', 'reason_category')
            ->toArray();

        $eventsByAction = TenantLifecycleEvent::query()
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->toArray();

        $distinctSuspendedTenants = TenantManualSuspension::query()
            ->where('status', TenantManualSuspension::STATUS_ACTIVE)
            ->distinct('tenant_id')
            ->count('tenant_id');

        return [
            'active_manual_suspensions' => $active,
            'lifted_manual_suspensions' => $lifted,
            'suspended_tenants' => $distinctSuspendedTenants,
            'active_by_reason_category' => $byCategory,
            'lifecycle_events_by_action' => $eventsByAction,
            'total_lifecycle_events' => TenantLifecycleEvent::query()->count(),
        ];
    }
}
