<?php

namespace App\Console\Commands;

use App\Services\Release\ProductionReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 13 — production:readiness-check.
 *
 * Human-readable or JSON report of environment/runtime readiness. Never prints
 * secret values (see ProductionReadinessService). Exit code:
 *   0 — PASS/WARN (unless --strict), 1 — FAIL or strict-with-warnings.
 */
class ProductionReadinessCheckCommand extends Command
{
    protected $signature = 'production:readiness-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Validate production readiness (env, key, debug, db, migrations, cache/session/queue, storage) without exposing secrets.';

    public function handle(ProductionReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Readiness Check');
            foreach ($report['checks'] as $check) {
                $this->line("[{$check['status']}] {$check['key']} — {$check['message']}");
            }
            $this->line("Overall: {$report['overall_status']}");
        }

        return $this->exitCode($report['overall_status']);
    }

    private function exitCode(string $overall): int
    {
        if ($overall === ProductionReadinessService::STATUS_FAIL) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $overall === ProductionReadinessService::STATUS_WARN) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
