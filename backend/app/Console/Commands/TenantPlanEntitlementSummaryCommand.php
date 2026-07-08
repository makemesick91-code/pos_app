<?php

namespace App\Console\Commands;

use App\Services\TenantPlan\TenantPlanSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 26 — tenant-plan:entitlement-summary.
 *
 * Read-only, secret-safe summary of the feature entitlement registry, active plan
 * assignments by plan, enabled entitlements per plan, and active tenant overrides
 * by key. Exit code 0.
 */
class TenantPlanEntitlementSummaryCommand extends Command
{
    protected $signature = 'tenant-plan:entitlement-summary {--json : Output JSON}';

    protected $description = 'Summarize feature entitlement governance (registry, assignments, overrides).';

    public function handle(TenantPlanSummaryService $service): int
    {
        $summary = $service->entitlementSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Feature Entitlement Summary');
        $this->line('Entitlement keys: '.count($summary['entitlement_keys']));
        $this->line('Active plan assignments: '.$summary['assignments']['total_active']);
        $this->line('Active entitlement overrides: '.$summary['total_active_overrides']);
        foreach ($summary['enabled_entitlements_per_plan'] as $plan => $count) {
            $this->line("  plan {$plan}: {$count} entitlements enabled");
        }

        return self::SUCCESS;
    }
}
