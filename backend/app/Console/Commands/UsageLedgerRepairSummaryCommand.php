<?php

namespace App\Console\Commands;

use App\Services\UsageLedgerAnomaly\UsageLedgerRepairSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 28 — usage-ledger:repair-summary. Read-only, redacted governed repair
 * history (ULR-R008, ULR-R012). Never mutates anything, never prints secrets.
 */
class UsageLedgerRepairSummaryCommand extends Command
{
    protected $signature = 'usage-ledger:repair-summary '
        .'{--tenant= : Restrict to a tenant id} '
        .'{--meter= : Restrict to a meter key} '
        .'{--json : Output JSON}';

    protected $description = 'Read-only, redacted governed usage-ledger repair history.';

    public function handle(UsageLedgerRepairSummaryService $service): int
    {
        $tenant = $this->option('tenant');
        $meter = $this->option('meter');
        $report = $service->summary(
            ($tenant === null || $tenant === '') ? null : (int) $tenant,
            ($meter === null || $meter === '') ? null : (string) $meter,
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Usage Ledger Repair Summary');
            $this->line("total_repairs={$report['total_repairs']} net_quantity_delta={$report['net_quantity_delta']}");
            foreach ($report['repairs'] as $r) {
                $this->line("  #{$r['id']} tenant={$r['tenant_id']} meter={$r['meter_key']} period={$r['period_key']} "
                    ."delta={$r['quantity_delta']} by={$r['applied_by']} — {$r['reason']}");
            }
        }

        return self::SUCCESS;
    }
}
