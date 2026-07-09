<?php

namespace App\Services\Observability;

use App\Models\Tenant;
use App\Services\SupportOperations\SupportTenantHealthService;

/**
 * Sprint 36 — tenant runtime health probe (OBS-R012).
 *
 * Tenant-isolated. Reuses the Sprint 35 SupportTenantHealthService for the
 * canonical, deterministic health computation (which already aggregates plan/
 * billing/payment/entitlement/onboarding/device-sync/incident state and always
 * lets a manual suspension win). This service adds nothing that mutates state and
 * never lifts a suspension or reactivates anything. No PII/secrets.
 */
class TenantRuntimeProbeService
{
    public function __construct(private readonly SupportTenantHealthService $health) {}

    /**
     * @return array<string, mixed>
     */
    public function probe(Tenant $tenant): array
    {
        $overview = $this->health->overview($tenant);

        return [
            'tenant_id' => $overview['tenant_id'],
            'tenant_code' => $overview['tenant_code'],
            'tenant_status' => $overview['tenant_status'],
            'manual_suspension_active' => $overview['manual_suspension_active'],
            'health_status' => $overview['health_status'],
            'reason_codes' => $overview['reason_codes'],
            'dimensions' => $overview['dimensions'],
            'source' => 'sprint35_support_tenant_health',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function probeMany(?int $limit = null): array
    {
        $limit = max(1, min($limit ?? (int) config('observability_governance.tenant_probe.default_limit', 25),
            (int) config('observability_governance.tenant_probe.max_limit', 100)));

        return Tenant::query()->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Tenant $t) => $this->health->briefStatus($t))
            ->all();
    }

    /**
     * Count tenants in a degraded-or-worse state (safe aggregate for metrics).
     */
    public function degradedCount(): int
    {
        $worst = ['degraded', 'blocked', 'critical'];
        $count = 0;
        foreach (Tenant::query()->get() as $tenant) {
            if (in_array($this->health->briefStatus($tenant)['health_status'], $worst, true)) {
                $count++;
            }
        }

        return $count;
    }
}
