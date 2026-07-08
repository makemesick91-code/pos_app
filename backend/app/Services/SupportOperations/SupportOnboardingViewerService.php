<?php

namespace App\Services\SupportOperations;

use App\Models\TenantProvisioningRun;

/**
 * Sprint 35 — read-only onboarding / provisioning viewer (SUP-R011).
 *
 * Reads the Sprint 33 tenant_provisioning_runs safely. It NEVER mutates the
 * provisioning lifecycle — retry/cancel remain the Sprint 33 governed services'
 * responsibility and are intentionally not wrapped here. Output is a safe status
 * summary; no seed data or PII is returned.
 */
class SupportOnboardingViewerService
{
    public function summary(int $tenantId, int $limit = 10): array
    {
        $runs = TenantProvisioningRun::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        $byStatus = [];
        foreach ($runs as $run) {
            $byStatus[$run->status] = ($byStatus[$run->status] ?? 0) + 1;
        }

        $latest = $runs->first();

        return [
            'read_only' => true,
            'run_count' => $runs->count(),
            'by_status' => $byStatus,
            'latest' => $latest === null ? null : [
                'status' => $latest->status,
                'requested_plan_code' => $latest->requested_plan_code ?? null,
                'trial_ends_at' => optional($latest->trial_ends_at ?? null)->toIso8601String(),
                'created_at' => optional($latest->created_at)->toIso8601String(),
                'completed_at' => optional($latest->completed_at ?? null)->toIso8601String(),
                'failed_at' => optional($latest->failed_at ?? null)->toIso8601String(),
            ],
        ];
    }
}
