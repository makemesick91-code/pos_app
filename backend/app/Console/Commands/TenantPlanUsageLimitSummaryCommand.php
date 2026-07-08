<?php

namespace App\Console\Commands;

use App\Services\TenantPlan\TenantPlanSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 26 — tenant-plan:usage-limit-summary.
 *
 * Read-only, secret-safe summary of the usage-limit registry: which limits are
 * meterable now (computed from real DB counts) and which are declared but
 * deferred, plus how many plans define each limit. Exit code 0.
 */
class TenantPlanUsageLimitSummaryCommand extends Command
{
    protected $signature = 'tenant-plan:usage-limit-summary {--json : Output JSON}';

    protected $description = 'Summarize usage limit governance (registry, meterable vs deferred, per-plan coverage).';

    public function handle(TenantPlanSummaryService $service): int
    {
        $summary = $service->usageLimitSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Usage Limit Summary');
        $this->line('Usage limit keys: '.count($summary['usage_limit_keys']));
        $this->line('Meterable limits: '.implode(', ', $summary['meterable_limits']));
        $this->line('Deferred limits: '.(implode(', ', $summary['deferred_limits']) ?: '(none)'));
        foreach ($summary['limits'] as $key => $info) {
            $this->line("  {$key}: {$info['plans_defining']} plans, ".($info['meterable'] ? 'meterable' : 'deferred'));
        }

        return self::SUCCESS;
    }
}
