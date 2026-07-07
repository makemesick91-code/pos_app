<?php

namespace App\Console\Commands;

use App\Services\Operations\ProductionOperationsHealthService;
use Illuminate\Console\Command;

/**
 * Sprint 19 — production:ops-health.
 *
 * Evaluates the required production health signals (backend/auth/tenant/product
 * sync/cashier/QRIS/offline-sync/receipt/inventory/reports-closing/subscription/
 * admin-onboarding plus backup-restore/support-SLA/release-rollback readiness)
 * into a secret-safe GO/WATCH/NO_GO decision. Never prints secrets, never
 * deploys, never runs real backup/restore, never sends real alerts, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO
 * / strict WATCH.
 */
class ProductionOpsHealthCommand extends Command
{
    protected $signature = 'production:ops-health
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate production operations health signals into a GO/WATCH/NO-GO decision.';

    public function handle(ProductionOperationsHealthService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Operations Health');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ProductionOperationsHealthService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ProductionOperationsHealthService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
