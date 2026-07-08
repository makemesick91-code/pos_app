<?php

namespace App\Console\Commands;

use App\Services\TenantLifecycle\TenantSuspensionSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 25 — tenant-lifecycle:suspension-summary.
 *
 * Read-only, secret-safe manual suspension governance summary: active/lifted
 * counts, suspended tenants, active-by-reason-category, and lifecycle events by
 * action. Never prints reasons verbatim or any secret.
 */
class TenantLifecycleSuspensionSummaryCommand extends Command
{
    protected $signature = 'tenant-lifecycle:suspension-summary {--json : Output JSON}';

    protected $description = 'Show the manual tenant suspension governance summary (secret-safe counts).';

    public function handle(TenantSuspensionSummaryService $service): int
    {
        $summary = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Tenant Manual Suspension Summary');
        $this->line('Active manual suspensions: '.$summary['active_manual_suspensions']);
        $this->line('Lifted manual suspensions: '.$summary['lifted_manual_suspensions']);
        $this->line('Suspended tenants: '.$summary['suspended_tenants']);
        $this->line('Total lifecycle events: '.$summary['total_lifecycle_events']);

        foreach ($summary['lifecycle_events_by_action'] as $action => $total) {
            $this->line("  event[{$action}]: {$total}");
        }

        return self::SUCCESS;
    }
}
