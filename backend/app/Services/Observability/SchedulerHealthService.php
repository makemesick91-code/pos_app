<?php

namespace App\Services\Observability;

use App\Models\ObservabilitySchedulerRun;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — scheduler/command heartbeat + staleness detection (OBS-R011).
 *
 * Records a run row per command and derives a deterministic health status from
 * config thresholds: a command whose last completion is older than
 * scheduler_stale_seconds is "stale"; a run still "started" past that window is
 * "stuck"; a completed run over scheduler_long_run_ms is "long-running". With no
 * runs recorded it is healthy (CI-safe on a fresh DB). No secrets/PII.
 */
class SchedulerHealthService
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WATCH = 'watch';
    public const STATUS_DEGRADED = 'degraded';

    public function __construct(private readonly ObservabilityRedactor $redactor) {}

    public function recordStart(string $command): ObservabilitySchedulerRun
    {
        return ObservabilitySchedulerRun::query()->create([
            'command_name' => $command,
            'status' => ObservabilitySchedulerRun::STATUS_STARTED,
            'started_at' => Carbon::now(),
        ]);
    }

    public function recordComplete(ObservabilitySchedulerRun $run, int $exitCode = 0, ?string $failureReason = null): ObservabilitySchedulerRun
    {
        $now = Carbon::now();
        $run->status = $exitCode === 0 ? ObservabilitySchedulerRun::STATUS_COMPLETED : ObservabilitySchedulerRun::STATUS_FAILED;
        $run->completed_at = $now;
        $run->duration_ms = $run->started_at !== null ? max(0, $now->diffInMilliseconds($run->started_at)) : null;
        $run->exit_code = $exitCode;
        $run->failure_reason = $this->redactor->redactText($failureReason, 300);
        $run->save();

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $staleSeconds = (int) config('observability_governance.thresholds.scheduler_stale_seconds', 3600);
        $longRunMs = (int) config('observability_governance.thresholds.scheduler_long_run_ms', 60000);
        $now = Carbon::now();

        $commands = ObservabilitySchedulerRun::query()
            ->select('command_name')
            ->distinct()
            ->pluck('command_name')
            ->all();

        if ($commands === []) {
            return [
                'status' => self::STATUS_HEALTHY,
                'reason_codes' => ['no_runs_recorded'],
                'commands' => [],
            ];
        }

        $status = self::STATUS_HEALTHY;
        $reasons = [];
        $rows = [];

        foreach ($commands as $command) {
            $latest = ObservabilitySchedulerRun::query()
                ->forCommand($command)
                ->orderByDesc('id')
                ->first();
            if ($latest === null) {
                continue;
            }

            $commandStatus = 'ok';
            $lastCompletedAt = $latest->completed_at;

            if ($latest->status === ObservabilitySchedulerRun::STATUS_FAILED) {
                $commandStatus = 'failed';
                $status = $this->worst($status, self::STATUS_DEGRADED);
                $reasons[] = 'command_failed';
            }

            if ($latest->status === ObservabilitySchedulerRun::STATUS_STARTED
                && $latest->started_at !== null
                && $latest->started_at->copy()->addSeconds($staleSeconds)->isPast($now)) {
                $commandStatus = 'stuck';
                $status = $this->worst($status, self::STATUS_DEGRADED);
                $reasons[] = 'command_stuck';
            }

            if ($lastCompletedAt !== null && $lastCompletedAt->copy()->addSeconds($staleSeconds)->isPast($now)) {
                $commandStatus = $commandStatus === 'ok' ? 'stale' : $commandStatus;
                $status = $this->worst($status, self::STATUS_WATCH);
                $reasons[] = 'command_stale';
            }

            if ($latest->duration_ms !== null && $latest->duration_ms >= $longRunMs) {
                $status = $this->worst($status, self::STATUS_WATCH);
                $reasons[] = 'command_long_running';
            }

            $rows[] = [
                'command_name' => $command,
                'last_status' => $latest->status,
                'command_health' => $commandStatus,
                'last_completed_at' => optional($lastCompletedAt)->toIso8601String(),
                'last_duration_ms' => $latest->duration_ms,
            ];
        }

        if ($reasons === []) {
            $reasons[] = 'no_issues_detected';
        }

        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'commands' => $rows,
        ];
    }

    private function worst(string $a, string $b): string
    {
        $rank = [self::STATUS_HEALTHY => 0, self::STATUS_WATCH => 1, self::STATUS_DEGRADED => 2];

        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }
}
