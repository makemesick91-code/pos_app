<?php

namespace App\Services\Observability;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sprint 36 — queue health (OBS-R008).
 *
 * Inspects the `jobs` (pending) and `failed_jobs` tables and derives a
 * deterministic status from config thresholds: pending backlog, oldest-pending
 * age, and failed-job count. NEVER reads or returns a job payload/exception —
 * only safe aggregate counts and ages. Tolerates missing tables (returns
 * status=healthy with a note) so it is CI-safe on the `sync` driver.
 */
class QueueHealthService
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WATCH = 'watch';
    public const STATUS_DEGRADED = 'degraded';

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $t = (array) config('observability_governance.thresholds', []);
        $reasons = [];
        $status = self::STATUS_HEALTHY;

        $pending = 0;
        $oldestPendingAgeSeconds = 0;
        $failed = 0;
        $jobsTablePresent = Schema::hasTable('jobs');
        $failedTablePresent = Schema::hasTable('failed_jobs');

        if ($jobsTablePresent) {
            try {
                $pending = (int) DB::table('jobs')->count();
                $oldestAvailable = DB::table('jobs')->min('available_at');
                if ($oldestAvailable !== null) {
                    $oldestPendingAgeSeconds = max(0, Carbon::now()->getTimestamp() - (int) $oldestAvailable);
                }
            } catch (Throwable) {
                // Leave counts at zero; a driver without the table is not a fault.
            }
        }

        if ($failedTablePresent) {
            try {
                $failed = (int) DB::table('failed_jobs')->count();
            } catch (Throwable) {
                // Ignore.
            }
        }

        // Pending backlog.
        if ($pending >= (int) ($t['queue_pending_degraded'] ?? 500)) {
            $status = $this->worst($status, self::STATUS_DEGRADED);
            $reasons[] = 'queue_backlog_high';
        } elseif ($pending >= (int) ($t['queue_pending_watch'] ?? 100)) {
            $status = $this->worst($status, self::STATUS_WATCH);
            $reasons[] = 'queue_backlog_watch';
        }

        // Oldest pending age.
        if ($oldestPendingAgeSeconds >= (int) ($t['queue_oldest_pending_degraded_seconds'] ?? 900)) {
            $status = $this->worst($status, self::STATUS_DEGRADED);
            $reasons[] = 'queue_oldest_pending_old';
        } elseif ($oldestPendingAgeSeconds >= (int) ($t['queue_oldest_pending_watch_seconds'] ?? 300)) {
            $status = $this->worst($status, self::STATUS_WATCH);
            $reasons[] = 'queue_oldest_pending_watch';
        }

        // Failed jobs.
        if ($failed >= (int) ($t['failed_jobs_degraded'] ?? 10)) {
            $status = $this->worst($status, self::STATUS_DEGRADED);
            $reasons[] = 'failed_jobs_high';
        } elseif ($failed >= (int) ($t['failed_jobs_watch'] ?? 1)) {
            $status = $this->worst($status, self::STATUS_WATCH);
            $reasons[] = 'failed_jobs_present';
        }

        if ($reasons === []) {
            $reasons[] = 'no_issues_detected';
        }

        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'metrics' => [
                'default_connection' => (string) config('queue.default'),
                'jobs_table_present' => $jobsTablePresent,
                'failed_jobs_table_present' => $failedTablePresent,
                'pending_jobs' => $pending,
                'oldest_pending_age_seconds' => $oldestPendingAgeSeconds,
                'failed_jobs' => $failed,
            ],
        ];
    }

    private function worst(string $a, string $b): string
    {
        $rank = [self::STATUS_HEALTHY => 0, self::STATUS_WATCH => 1, self::STATUS_DEGRADED => 2];

        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }
}
