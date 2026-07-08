<?php

namespace App\Console\Commands;

use App\Services\UsageLedgerAnomaly\UsageLedgerRepairDecision;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairPlanner;
use Illuminate\Console\Command;

/**
 * Sprint 28 — usage-ledger:repair-plan. DRY-RUN ONLY (ULR-R007). Produces a
 * redacted governed repair plan: which anomalies are auto-repairable and which are
 * manual-review-only (ULR-R010). Never writes to the database, never prints
 * secrets. Exit code 0.
 */
class UsageLedgerRepairPlanCommand extends Command
{
    protected $signature = 'usage-ledger:repair-plan '
        .'{--tenant= : Restrict to a tenant id} '
        .'{--meter= : Restrict to a meter key} '
        .'{--reason= : Reason for the proposed repair plan} '
        .'{--json : Output JSON}';

    protected $description = 'Dry-run governed repair plan for usage-ledger anomalies (read-only).';

    public function handle(UsageLedgerRepairPlanner $planner): int
    {
        $tenant = $this->option('tenant');
        $meter = $this->option('meter');
        $decisions = $planner->plan(
            ($tenant === null || $tenant === '') ? null : (int) $tenant,
            ($meter === null || $meter === '') ? null : (string) $meter,
        );

        $auto = array_values(array_filter($decisions, fn (UsageLedgerRepairDecision $d) => $d->isAutoRepairable()));
        $manual = array_values(array_filter($decisions, fn (UsageLedgerRepairDecision $d) => ! $d->isAutoRepairable()));

        $report = [
            'dry_run' => true,
            'reason' => (string) ($this->option('reason') ?? ''),
            'total' => count($decisions),
            'auto_repairable' => count($auto),
            'manual_review' => count($manual),
            'decisions' => array_map(fn (UsageLedgerRepairDecision $d) => $d->toArray(), $decisions),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Usage Ledger Repair Plan (DRY-RUN, no DB writes)');
            $this->line("total={$report['total']} auto_repairable={$report['auto_repairable']} manual_review={$report['manual_review']}");
            foreach ($decisions as $d) {
                $this->line("[{$d->action}] {$d->anomalyType} tenant=".($d->tenantId ?? '-')
                    .' delta='.$d->quantityDelta.' — '.$d->summary);
            }
        }

        return self::SUCCESS;
    }
}
