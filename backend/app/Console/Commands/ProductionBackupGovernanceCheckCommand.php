<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupRestoreGovernanceService;
use Illuminate\Console\Command;

/**
 * Sprint 19 — production:backup-governance-check.
 *
 * Verifies the backup/restore governance documentation exists and covers the
 * required sections, and that the supporting runbook/handover docs are present.
 * This is a governance/documentation check only: it never runs a real backup,
 * never runs a real restore, and never prints DB credentials. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class ProductionBackupGovernanceCheckCommand extends Command
{
    protected $signature = 'production:backup-governance-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Check backup/restore governance docs/evidence into a GO/WATCH/NO-GO decision.';

    public function handle(BackupRestoreGovernanceService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Backup/Restore Governance Check');
            foreach ($report['checks'] as $check) {
                $this->line("[{$check['status']}] {$check['key']} — {$check['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === BackupRestoreGovernanceService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === BackupRestoreGovernanceService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
