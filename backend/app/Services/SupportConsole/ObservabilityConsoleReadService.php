<?php

namespace App\Services\SupportConsole;

use App\Models\ObservabilityHealthSnapshot;
use App\Services\Observability\FailedJobDiagnosticsService;
use App\Services\Observability\InfrastructureHealthCheckService;
use App\Services\Observability\ObservabilityAnomalyScanService;
use App\Services\Observability\ObservabilityHealthService;
use App\Services\Observability\ObservabilityIncidentSuggestionService;
use App\Services\Observability\ObservabilityMetricsService;
use App\Services\Observability\QueueHealthService;
use App\Services\Observability\SchedulerHealthService;
use App\Services\Observability\TenantRuntimeProbeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UIX-6 — read adapter for the Platform Admin Observability Console
 * (`/admin/observability`). It orchestrates the EXISTING Sprint 36 canonical
 * observability services and shapes their output for Blade (UIX6-R001/R002).
 * It never recomputes health, queue, scheduler, anomaly, or metric state.
 *
 * Its one presentational responsibility beyond shaping is TRUTHFUL FRESHNESS
 * (UIX6-R011/R012/R013): the canonical {@see ObservabilityHealthService} folds a
 * scheduler that never ran (`no_runs_recorded`) and a missing subsystem into an
 * aggregate "healthy". A support console must not paint that green. So each
 * component is presented with an explicit `display_status` that becomes
 * `unknown` when the underlying evidence is absent/stale, and the persisted
 * snapshot's `checked_at` is surfaced as the freshness `as_of`.
 */
class ObservabilityConsoleReadService
{
    /** Health data older than this (persisted snapshot) is presented as stale. */
    public const FRESHNESS_TTL_SECONDS = 900;

    /** Truthful presentation status used when evidence is absent/stale. */
    public const DISPLAY_UNKNOWN = 'unknown';

    public function __construct(
        private readonly ObservabilityHealthService $health,
        private readonly InfrastructureHealthCheckService $infrastructure,
        private readonly QueueHealthService $queue,
        private readonly SchedulerHealthService $scheduler,
        private readonly FailedJobDiagnosticsService $failedJobs,
        private readonly ObservabilityMetricsService $metrics,
        private readonly TenantRuntimeProbeService $tenantProbe,
        private readonly ObservabilityAnomalyScanService $anomalies,
        private readonly ObservabilityIncidentSuggestionService $suggestions,
    ) {}

    /**
     * The consolidated observability overview view model.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'health' => $this->presentHealth(),
            'queue' => $this->safe(fn () => $this->queue->summary()),
            'scheduler' => $this->safe(fn () => $this->scheduler->summary()),
            'failed_jobs' => $this->safe(fn () => $this->failedJobs->summary(50)),
            'metrics' => $this->safe(fn () => $this->metrics->summary()),
            'tenants' => $this->safe(fn () => [
                'degraded' => $this->tenantProbe->degradedCount(),
            ]),
            'anomalies' => $this->safe(fn () => ['items' => $this->anomalies->detectAll()]),
            'alerts' => $this->safe(fn () => ['items' => $this->suggestions->list()]),
        ];
    }

    /**
     * Present application + per-component health with truthful freshness. The
     * live aggregate status comes straight from the canonical service; the
     * `display_status` values never claim "healthy" for a component whose
     * evidence is missing or stale (UIX6-R011).
     *
     * @return array<string, mixed>
     */
    private function presentHealth(): array
    {
        try {
            $overview = $this->health->overview();
        } catch (Throwable $e) {
            Log::warning('admin.observability.health_unavailable', ['exception' => $e::class]);

            return ['available' => false];
        }

        $components = $overview['components'] ?? [];
        $infra = $components['infrastructure'] ?? [];
        $queue = $components['queue'] ?? [];
        $scheduler = $components['scheduler'] ?? [];

        $snapshot = $this->latestApplicationSnapshot();
        $asOf = $snapshot?->checked_at;
        $ageSeconds = $asOf ? (int) $asOf->diffInSeconds(Carbon::now()) : null;
        $snapshotFresh = $ageSeconds !== null && $ageSeconds <= self::FRESHNESS_TTL_SECONDS;

        return [
            'available' => true,
            'status' => $overview['status'] ?? self::DISPLAY_UNKNOWN,
            'reason_codes' => $overview['reason_codes'] ?? [],
            'checked_at' => $overview['checked_at'] ?? null,
            'snapshot_as_of' => optional($asOf)->toIso8601String(),
            'snapshot_fresh' => $snapshotFresh,
            'snapshot_stale' => $asOf !== null && ! $snapshotFresh,
            'snapshot_missing' => $asOf === null,
            'components' => [
                'infrastructure' => $this->presentInfrastructure($infra),
                'queue' => $this->presentQueue($queue),
                'scheduler' => $this->presentScheduler($scheduler),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $infra
     * @return array<string, mixed>
     */
    private function presentInfrastructure(array $infra): array
    {
        $status = $infra['status'] ?? null;
        $display = match ($status) {
            InfrastructureHealthCheckService::STATUS_OK => 'healthy',
            InfrastructureHealthCheckService::STATUS_ERROR => 'critical',
            default => self::DISPLAY_UNKNOWN,
        };

        return ['display_status' => $display, 'known' => $status !== null];
    }

    /**
     * A queue whose jobs table is absent cannot be asserted healthy (UIX6-R011).
     *
     * @param  array<string, mixed>  $queue
     * @return array<string, mixed>
     */
    private function presentQueue(array $queue): array
    {
        $present = (bool) ($queue['jobs_table_present'] ?? false);
        $display = $present ? ($queue['status'] ?? self::DISPLAY_UNKNOWN) : self::DISPLAY_UNKNOWN;

        return [
            'display_status' => $display,
            'known' => $present,
            'pending_jobs' => $present ? (int) ($queue['pending_jobs'] ?? 0) : null,
            'failed_jobs' => $present ? (int) ($queue['failed_jobs'] ?? 0) : null,
        ];
    }

    /**
     * A scheduler that has never recorded a run is UNKNOWN, not healthy — the
     * canonical aggregate deliberately drops `no_runs_recorded`, so we restore
     * that truth here (UIX6-R011/R012).
     *
     * @param  array<string, mixed>  $scheduler
     * @return array<string, mixed>
     */
    private function presentScheduler(array $scheduler): array
    {
        $reasons = $scheduler['reason_codes'] ?? [];
        $noRuns = in_array('no_runs_recorded', $reasons, true);
        $display = $noRuns ? self::DISPLAY_UNKNOWN : ($scheduler['status'] ?? self::DISPLAY_UNKNOWN);

        return [
            'display_status' => $display,
            'known' => ! $noRuns,
            'reason_codes' => $reasons,
        ];
    }

    private function latestApplicationSnapshot(): ?ObservabilityHealthSnapshot
    {
        try {
            return ObservabilityHealthSnapshot::query()
                ->forScope(ObservabilityHealthSnapshot::SCOPE_APPLICATION)
                ->orderByDesc('checked_at')
                ->first();
        } catch (Throwable $e) {
            Log::warning('admin.observability.snapshot_unavailable', ['exception' => $e::class]);

            return null;
        }
    }

    /**
     * Degrade a failed downstream read to a truthful unavailable panel rather
     * than a fabricated zero/healthy (UIX6-R013).
     *
     * @param  callable(): mixed  $read
     * @return array<string, mixed>
     */
    private function safe(callable $read): array
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            Log::warning('admin.observability.panel_unavailable', ['exception' => $e::class]);

            return ['available' => false];
        }

        if (is_array($value)) {
            return ['available' => true] + $value;
        }

        return ['available' => true, 'value' => $value];
    }
}
