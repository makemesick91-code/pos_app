<?php

namespace App\Console\Commands;

use App\Services\RuntimeMaintenance\RuntimeMaintenanceService;
use Illuminate\Console\Command;

/**
 * Safe pruning of expired database sessions. Dry-run by default; only removes
 * sessions older than max(retention, session lifetime) so active sessions are
 * never touched. Logs counts only — never session payloads.
 */
class PilotPruneSessionsCommand extends Command
{
    protected $signature = 'pilot:prune-sessions
        {--hours=168 : Retention window in hours (clamped up to the session lifetime)}
        {--apply : Actually delete (default is dry-run)}
        {--chunk=1000 : Delete chunk size}
        {--json : Output JSON}';

    protected $description = 'Prune expired database sessions safely (dry-run by default).';

    public function handle(RuntimeMaintenanceService $service): int
    {
        $hours = (int) $this->option('hours');
        $chunk = (int) $this->option('chunk');
        $dryRun = ! $this->option('apply');

        if ($hours < 1) {
            $this->error('--hours must be >= 1');

            return self::FAILURE;
        }

        try {
            $result = $service->pruneSessions($hours, $dryRun, $chunk);
        } catch (\Throwable $e) {
            $this->error('prune-sessions failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $mode = $result['dry_run'] ? 'DRY-RUN' : 'APPLIED';
        $this->line("[{$mode}] prune-sessions retention={$result['retention_hours']}h candidates={$result['candidates']} deleted={$result['deleted']}");

        return self::SUCCESS;
    }
}
