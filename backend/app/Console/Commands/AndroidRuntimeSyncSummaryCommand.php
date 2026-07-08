<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:sync-summary. Safe summary of sync batches/items,
 * failures and conflicts. No PII/secrets (ADR-R020/R022).
 */
class AndroidRuntimeSyncSummaryCommand extends Command
{
    protected $signature = 'android-runtime:sync-summary {--tenant= : Scope to a tenant id} {--json : Output JSON}';

    protected $description = 'Show a safe summary of Android sync batches and item outcomes.';

    public function handle(AndroidRuntimeSummaryService $summary): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $report = $summary->syncSummary($tenantId);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Sync batches: '.$report['batches']['total']);
        foreach ($report['batches']['by_status'] as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
        $this->line('Items accepted='.$report['batches']['accepted_items']
            .' duplicate='.$report['batches']['duplicate_items']
            .' conflict='.$report['batches']['conflict_items']
            .' failed='.$report['batches']['failed_items']);

        return self::SUCCESS;
    }
}
