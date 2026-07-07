<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotMonitoringService;
use Illuminate\Console\Command;

/**
 * Sprint 16 — pilot:daily-monitoring-check.
 *
 * Aggregates the daily pilot monitoring signals (backend health, auth/login,
 * tenant context, product sync, cashier cash sale, QRIS status, receipt/printer,
 * offline cash queue/retry, inventory, reports, closing, subscription/device,
 * admin/onboarding, demo reset guard) plus monitoring/hypercare doc presence and
 * the cumulative release/pilot command contract into a GO / WATCH / NO-GO
 * decision. Does NOT run Android Gradle, never mutates production data, never
 * sends real alerts, and never prints secrets. Exit code: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotDailyMonitoringCheckCommand extends Command
{
    protected $signature = 'pilot:daily-monitoring-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate daily pilot monitoring signals into a GO/WATCH/NO-GO decision.';

    public function handle(PilotMonitoringService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Daily Monitoring Check');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line("Decision: {$report['decision']}");
        }

        return $this->exitCode($report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotMonitoringService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotMonitoringService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
