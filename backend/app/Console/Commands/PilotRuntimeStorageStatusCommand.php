<?php

namespace App\Console\Commands;

use App\Services\RuntimeMaintenance\RuntimeMaintenanceService;
use Illuminate\Console\Command;

/**
 * Read-only runtime storage status for the database-backed queue/cache/session
 * drivers. Never persists, never prints secrets, never deploys. Emits a
 * GO/WATCH/NO-GO decision based on failed-job count and oldest pending job age.
 */
class PilotRuntimeStorageStatusCommand extends Command
{
    protected $signature = 'pilot:runtime-storage-status
        {--json : Output JSON}
        {--strict : Exit non-zero on WATCH as well as NO-GO}';

    protected $description = 'Report runtime table counts, sizes and queue health for the Aish POS pilot (read-only).';

    public function handle(RuntimeMaintenanceService $service): int
    {
        $report = $service->status();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode((string) $report['decision']);
        }

        $this->line('Runtime Storage Status');
        $this->line('Database: '.$report['database'].' (driver='.$report['driver'].')');
        $this->line('Database size: '.($report['database_size_pretty'] ?? 'n/a'));
        $this->newLine();

        $this->line('Tables:');
        foreach ($report['tables'] as $name => $t) {
            if (! $t['exists']) {
                $this->line(sprintf('  %-14s (missing)', $name));

                continue;
            }
            $this->line(sprintf('  %-14s rows=%-8s size=%s', $name, $t['rows'], $t['size_pretty'] ?? 'n/a'));
        }
        $this->newLine();

        $oldest = $report['queue']['oldest_pending_seconds'];
        $this->line('Queue: pending='.$report['queue']['pending']
            .' reserved='.$report['queue']['reserved']
            .' oldest_pending='.($oldest === null ? 'none' : $oldest.'s'));
        $this->line('Failed jobs: '.$report['failed_jobs']['count']);
        $this->line('Sessions: '.$report['sessions']['count']
            .' (expired est '.$report['sessions']['expired_estimate']
            .', lifetime '.$report['sessions']['lifetime_minutes'].'m)');
        $this->line('Cache: '.$report['cache']['count'].' (expired est '.$report['cache']['expired_estimate'].')');
        $this->newLine();

        foreach ($report['signals'] as $signal) {
            $this->line('['.$signal['status'].'] '.$signal['key'].' — '.$signal['detail']);
        }
        $this->line('Decision: '.$report['decision']);

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === RuntimeMaintenanceService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $decision === RuntimeMaintenanceService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
