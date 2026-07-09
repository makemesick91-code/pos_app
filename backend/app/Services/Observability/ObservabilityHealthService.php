<?php

namespace App\Services\Observability;

use App\Models\ObservabilityHealthSnapshot;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — the application-level health aggregation (OBS-R020).
 *
 * Combines infrastructure, queue and scheduler health into ONE deterministic,
 * explainable status with reason codes and safe aggregate metrics. Optionally
 * persists a redacted snapshot. Never returns raw payloads, credentials or PII.
 */
class ObservabilityHealthService
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WATCH = 'watch';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_CRITICAL = 'critical';

    private const RANK = [
        self::STATUS_HEALTHY => 0,
        self::STATUS_WATCH => 1,
        self::STATUS_DEGRADED => 2,
        self::STATUS_BLOCKED => 3,
        self::STATUS_CRITICAL => 4,
    ];

    public function __construct(
        private readonly InfrastructureHealthCheckService $infrastructure,
        private readonly QueueHealthService $queue,
        private readonly SchedulerHealthService $scheduler,
        private readonly ObservabilityRedactor $redactor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $infra = $this->infrastructure->check();
        $queue = $this->queue->summary();
        $scheduler = $this->scheduler->summary();

        $status = self::STATUS_HEALTHY;
        $reasons = [];

        if (($infra['status'] ?? 'error') !== InfrastructureHealthCheckService::STATUS_OK) {
            $status = $this->worst($status, self::STATUS_CRITICAL);
            $reasons[] = 'infrastructure_error';
        }

        $status = $this->worst($status, $this->normalize($queue['status'] ?? 'healthy'));
        foreach (($queue['reason_codes'] ?? []) as $r) {
            if ($r !== 'no_issues_detected') {
                $reasons[] = 'queue_'.$r;
            }
        }

        $status = $this->worst($status, $this->normalize($scheduler['status'] ?? 'healthy'));
        foreach (($scheduler['reason_codes'] ?? []) as $r) {
            if (! in_array($r, ['no_issues_detected', 'no_runs_recorded'], true)) {
                $reasons[] = 'scheduler_'.$r;
            }
        }

        if ($reasons === []) {
            $reasons[] = 'no_issues_detected';
        }

        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'checked_at' => Carbon::now()->toIso8601String(),
            'components' => [
                'infrastructure' => $infra,
                'queue' => $queue,
                'scheduler' => $scheduler,
            ],
        ];
    }

    /**
     * A minimal, non-tenant readiness view for the public /health/ready endpoint.
     *
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $infra = $this->infrastructure->check();
        $ready = ($infra['status'] ?? 'error') === InfrastructureHealthCheckService::STATUS_OK;

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'checked_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Persist a redacted application health snapshot (OBS-R003/R004).
     */
    public function snapshot(): ObservabilityHealthSnapshot
    {
        $overview = $this->overview();

        return ObservabilityHealthSnapshot::query()->create([
            'scope_type' => ObservabilityHealthSnapshot::SCOPE_APPLICATION,
            'status' => $overview['status'],
            'reason_code' => implode(',', $overview['reason_codes']),
            'summary_safe' => 'application health '.$overview['status'],
            'metrics_json' => $this->redactor->redact([
                'queue' => $overview['components']['queue']['metrics'] ?? [],
                'infrastructure_status' => $overview['components']['infrastructure']['status'] ?? null,
                'scheduler_status' => $overview['components']['scheduler']['status'] ?? null,
            ]),
            'checked_at' => Carbon::now(),
            'metadata_json' => ['reason_codes' => $overview['reason_codes']],
        ]);
    }

    private function normalize(string $status): string
    {
        return array_key_exists($status, self::RANK) ? $status : self::STATUS_WATCH;
    }

    private function worst(string $a, string $b): string
    {
        return (self::RANK[$a] ?? 0) >= (self::RANK[$b] ?? 0) ? $a : $b;
    }
}
