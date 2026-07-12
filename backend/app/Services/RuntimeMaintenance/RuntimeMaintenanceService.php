<?php

namespace App\Services\RuntimeMaintenance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only runtime storage inspection + safe pruning for the database-backed
 * queue / cache / session drivers used by the Aish POS shared-VPS pilot.
 *
 * Contract:
 *  - status() never mutates, never prints secrets, never touches DaengtisiaMS.
 *  - Prune helpers operate only on the framework runtime tables and never
 *    delete active/reserved jobs or sessions newer than the retention window.
 *  - PostgreSQL-specific size probes are guarded by driver; on other drivers
 *    (e.g. the sqlite test database) sizes are reported as null, not an error.
 */
class RuntimeMaintenanceService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    /** Failed-jobs thresholds (pilot defaults). */
    public const FAILED_WATCH = 10;
    public const FAILED_NO_GO = 100;

    /** Oldest-pending-job thresholds in seconds (5 min WATCH, 30 min NO-GO). */
    public const OLDEST_JOB_WATCH_SECONDS = 300;
    public const OLDEST_JOB_NO_GO_SECONDS = 1800;

    /** Runtime tables this service knows how to inspect. */
    private const RUNTIME_TABLES = [
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
        'cache',
        'cache_locks',
    ];

    /**
     * Build a full read-only runtime storage report with a GO/WATCH/NO-GO
     * decision driven by failed-job count and oldest pending job age.
     *
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $now = time();
        $isPg = $driver === 'pgsql';

        $tables = [];
        foreach (self::RUNTIME_TABLES as $table) {
            $exists = Schema::hasTable($table);
            $sizeBytes = ($exists && $isPg) ? $this->pgRelationSize($table) : null;
            $tables[$table] = [
                'exists' => $exists,
                'rows' => $exists ? (int) DB::table($table)->count() : null,
                'size_bytes' => $sizeBytes,
                'size_pretty' => $sizeBytes === null ? null : $this->humanBytes($sizeBytes),
            ];
        }

        $queue = $this->queueMetrics($now);
        $failedCount = $tables['failed_jobs']['exists'] ? (int) DB::table('failed_jobs')->count() : 0;
        $sessions = $this->sessionMetrics($now);
        $cache = $this->cacheMetrics($now);

        $databaseSizeBytes = $isPg ? $this->pgDatabaseSize($connection->getDatabaseName()) : null;

        $signals = $this->evaluateSignals($failedCount, $queue['oldest_pending_seconds']);
        $decision = $this->decisionFrom($signals);

        return [
            'database' => $connection->getDatabaseName(),
            'driver' => $driver,
            'tables' => $tables,
            'queue' => $queue,
            'failed_jobs' => ['count' => $failedCount],
            'sessions' => $sessions,
            'cache' => $cache,
            'database_size_bytes' => $databaseSizeBytes,
            'database_size_pretty' => $databaseSizeBytes === null ? null : $this->humanBytes($databaseSizeBytes),
            'thresholds' => [
                'failed_jobs_watch' => self::FAILED_WATCH,
                'failed_jobs_no_go' => self::FAILED_NO_GO,
                'oldest_pending_seconds_watch' => self::OLDEST_JOB_WATCH_SECONDS,
                'oldest_pending_seconds_no_go' => self::OLDEST_JOB_NO_GO_SECONDS,
            ],
            'signals' => $signals,
            'decision' => $decision,
        ];
    }

    /**
     * @return array{pending:int,reserved:int,oldest_pending_seconds:?int}
     */
    private function queueMetrics(int $now): array
    {
        if (! Schema::hasTable('jobs')) {
            return ['pending' => 0, 'reserved' => 0, 'oldest_pending_seconds' => null];
        }

        $reserved = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
        $pending = (int) DB::table('jobs')->whereNull('reserved_at')->count();

        $oldestAvailable = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->min('available_at');

        $oldestSeconds = $oldestAvailable === null ? null : max(0, $now - (int) $oldestAvailable);

        return [
            'pending' => $pending,
            'reserved' => $reserved,
            'oldest_pending_seconds' => $oldestSeconds,
        ];
    }

    /**
     * @return array{count:int,expired_estimate:int,lifetime_minutes:int}
     */
    private function sessionMetrics(int $now): array
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);

        if (! Schema::hasTable('sessions')) {
            return ['count' => 0, 'expired_estimate' => 0, 'lifetime_minutes' => $lifetimeMinutes];
        }

        $threshold = $now - ($lifetimeMinutes * 60);

        return [
            'count' => (int) DB::table('sessions')->count(),
            'expired_estimate' => (int) DB::table('sessions')->where('last_activity', '<', $threshold)->count(),
            'lifetime_minutes' => $lifetimeMinutes,
        ];
    }

    /**
     * @return array{count:int,expired_estimate:int}
     */
    private function cacheMetrics(int $now): array
    {
        if (! Schema::hasTable('cache')) {
            return ['count' => 0, 'expired_estimate' => 0];
        }

        return [
            'count' => (int) DB::table('cache')->count(),
            'expired_estimate' => (int) DB::table('cache')->where('expiration', '<=', $now)->count(),
        ];
    }

    /**
     * @return array<int,array{key:string,status:string,detail:string}>
     */
    private function evaluateSignals(int $failedCount, ?int $oldestPendingSeconds): array
    {
        $signals = [];

        if ($failedCount > self::FAILED_NO_GO) {
            $signals[] = ['key' => 'failed_jobs', 'status' => self::DECISION_NO_GO, 'detail' => "failed_jobs={$failedCount} > ".self::FAILED_NO_GO];
        } elseif ($failedCount > self::FAILED_WATCH) {
            $signals[] = ['key' => 'failed_jobs', 'status' => self::DECISION_WATCH, 'detail' => "failed_jobs={$failedCount} > ".self::FAILED_WATCH];
        } else {
            $signals[] = ['key' => 'failed_jobs', 'status' => self::DECISION_GO, 'detail' => "failed_jobs={$failedCount}"];
        }

        if ($oldestPendingSeconds === null) {
            $signals[] = ['key' => 'oldest_pending_job', 'status' => self::DECISION_GO, 'detail' => 'no pending jobs'];
        } elseif ($oldestPendingSeconds > self::OLDEST_JOB_NO_GO_SECONDS) {
            $signals[] = ['key' => 'oldest_pending_job', 'status' => self::DECISION_NO_GO, 'detail' => "oldest_pending={$oldestPendingSeconds}s > ".self::OLDEST_JOB_NO_GO_SECONDS.'s'];
        } elseif ($oldestPendingSeconds > self::OLDEST_JOB_WATCH_SECONDS) {
            $signals[] = ['key' => 'oldest_pending_job', 'status' => self::DECISION_WATCH, 'detail' => "oldest_pending={$oldestPendingSeconds}s > ".self::OLDEST_JOB_WATCH_SECONDS.'s'];
        } else {
            $signals[] = ['key' => 'oldest_pending_job', 'status' => self::DECISION_GO, 'detail' => "oldest_pending={$oldestPendingSeconds}s"];
        }

        return $signals;
    }

    /**
     * @param  array<int,array{key:string,status:string,detail:string}>  $signals
     */
    private function decisionFrom(array $signals): string
    {
        $statuses = array_column($signals, 'status');
        if (in_array(self::DECISION_NO_GO, $statuses, true)) {
            return self::DECISION_NO_GO;
        }
        if (in_array(self::DECISION_WATCH, $statuses, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /**
     * Delete expired sessions older than the retention window.
     * Retention is max(session lifetime, requested hours) so active sessions
     * are never removed. Processes in chunks to avoid long locks.
     *
     * @return array{driver:string,dry_run:bool,retention_hours:int,threshold:int,candidates:int,deleted:int}
     */
    public function pruneSessions(int $retentionHours, bool $dryRun, int $chunk = 1000): array
    {
        $now = time();
        $lifetimeHours = (int) ceil(((int) config('session.lifetime', 120)) / 60);
        $effectiveHours = max($retentionHours, $lifetimeHours);
        $threshold = $now - ($effectiveHours * 3600);

        $result = [
            'driver' => DB::connection()->getDriverName(),
            'dry_run' => $dryRun,
            'retention_hours' => $effectiveHours,
            'threshold' => $threshold,
            'candidates' => 0,
            'deleted' => 0,
        ];

        if (! Schema::hasTable('sessions')) {
            return $result;
        }

        $result['candidates'] = (int) DB::table('sessions')->where('last_activity', '<', $threshold)->count();

        if ($dryRun) {
            return $result;
        }

        $result['deleted'] = $this->chunkedDelete('sessions', fn ($q) => $q->where('last_activity', '<', $threshold), $chunk);

        return $result;
    }

    /**
     * Delete expired database-cache rows (expiration in the past) and expired
     * cache locks. Never touches unexpired entries. Chunked deletes.
     *
     * @return array{driver:string,dry_run:bool,threshold:int,cache_candidates:int,cache_deleted:int,lock_candidates:int,lock_deleted:int}
     */
    public function pruneCache(bool $dryRun, int $chunk = 1000): array
    {
        $now = time();

        $result = [
            'driver' => DB::connection()->getDriverName(),
            'dry_run' => $dryRun,
            'threshold' => $now,
            'cache_candidates' => 0,
            'cache_deleted' => 0,
            'lock_candidates' => 0,
            'lock_deleted' => 0,
        ];

        if (Schema::hasTable('cache')) {
            $result['cache_candidates'] = (int) DB::table('cache')->where('expiration', '<=', $now)->count();
            if (! $dryRun) {
                $result['cache_deleted'] = $this->chunkedDelete('cache', fn ($q) => $q->where('expiration', '<=', $now), $chunk);
            }
        }

        if (Schema::hasTable('cache_locks')) {
            $result['lock_candidates'] = (int) DB::table('cache_locks')->where('expiration', '<=', $now)->count();
            if (! $dryRun) {
                $result['lock_deleted'] = $this->chunkedDelete('cache_locks', fn ($q) => $q->where('expiration', '<=', $now), $chunk);
            }
        }

        return $result;
    }

    /**
     * Delete rows matching $where in bounded chunks, returning total deleted.
     *
     * @param  callable(\Illuminate\Database\Query\Builder):\Illuminate\Database\Query\Builder  $where
     */
    private function chunkedDelete(string $table, callable $where, int $chunk): int
    {
        $chunk = max(1, $chunk);
        $total = 0;

        do {
            $ids = $where(DB::table($table))->limit($chunk)->pluck($this->rowKey($table))->all();
            if ($ids === []) {
                break;
            }
            $total += DB::table($table)->whereIn($this->rowKey($table), $ids)->delete();
        } while (count($ids) === $chunk);

        return $total;
    }

    private function rowKey(string $table): string
    {
        // sessions/cache/cache_locks use a string primary key named accordingly.
        return match ($table) {
            'cache', 'cache_locks' => 'key',
            default => 'id',
        };
    }

    private function pgRelationSize(string $table): ?int
    {
        $row = DB::selectOne('SELECT pg_total_relation_size(?) AS bytes', [$table]);

        return $row && $row->bytes !== null ? (int) $row->bytes : null;
    }

    private function pgDatabaseSize(string $database): ?int
    {
        $row = DB::selectOne('SELECT pg_database_size(?) AS bytes', [$database]);

        return $row && $row->bytes !== null ? (int) $row->bytes : null;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $value, $units[$i]);
    }
}
