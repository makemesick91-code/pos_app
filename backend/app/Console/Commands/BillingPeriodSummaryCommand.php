<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\BillingPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:period-summary. Read-only. Shows the canonical billing
 * period (key, start, end, due date) for a date or explicit period key. Never
 * mutates anything.
 */
class BillingPeriodSummaryCommand extends Command
{
    protected $signature = 'billing:period-summary
        {--date= : Resolve the period containing this date (Y-m-d)}
        {--period= : Resolve an explicit period key (YYYY-MM)}
        {--json : Output JSON}';

    protected $description = 'Show the canonical, deterministic billing period (key, start, end, due date).';

    public function handle(BillingPeriodService $periods): int
    {
        try {
            if ($this->option('period')) {
                $period = $periods->resolveForKey((string) $this->option('period'));
            } elseif ($this->option('date')) {
                $period = $periods->resolveForDate(CarbonImmutable::parse((string) $this->option('date')));
            } else {
                $period = $periods->resolveForDate();
            }
        } catch (BillingGovernanceException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($period->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Billing Period Summary');
        foreach ($period->toArray() as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        return self::SUCCESS;
    }
}
