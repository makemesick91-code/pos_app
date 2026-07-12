<?php

namespace App\Console\Commands;

use App\Services\RuntimeMaintenance\RuntimeMaintenanceService;
use Illuminate\Console\Command;

/**
 * Safe pruning of expired database cache rows and cache locks. Dry-run by
 * default; only removes rows whose expiration is already in the past. The
 * framework `cache:prune-stale-tags` command is a no-op on the database driver,
 * so this handles expired-row reclamation. Logs counts only.
 */
class PilotPruneCacheCommand extends Command
{
    protected $signature = 'pilot:prune-cache
        {--apply : Actually delete (default is dry-run)}
        {--chunk=1000 : Delete chunk size}
        {--json : Output JSON}';

    protected $description = 'Prune expired database cache rows and locks safely (dry-run by default).';

    public function handle(RuntimeMaintenanceService $service): int
    {
        $chunk = (int) $this->option('chunk');
        $dryRun = ! $this->option('apply');

        try {
            $result = $service->pruneCache($dryRun, $chunk);
        } catch (\Throwable $e) {
            $this->error('prune-cache failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $mode = $result['dry_run'] ? 'DRY-RUN' : 'APPLIED';
        $this->line("[{$mode}] prune-cache cache_candidates={$result['cache_candidates']} cache_deleted={$result['cache_deleted']} lock_candidates={$result['lock_candidates']} lock_deleted={$result['lock_deleted']}");

        return self::SUCCESS;
    }
}
