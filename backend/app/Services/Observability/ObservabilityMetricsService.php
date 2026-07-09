<?php

namespace App\Services\Observability;

use App\Models\ObservabilityAlertSuggestion;
use App\Models\ObservabilityAnomalyEvent;
use App\Models\TenantSupportIncident;

/**
 * Sprint 36 — safe operational dashboard metrics (OBS-R023).
 *
 * Produces only aggregate counts: health status, failed jobs by reason, scheduler
 * freshness, degraded tenant count, sync failure count, webhook-invalid count,
 * onboarding failed count and open support incidents. NEVER returns a raw payload
 * or PII.
 */
class ObservabilityMetricsService
{
    public function __construct(
        private readonly ObservabilityHealthService $health,
        private readonly QueueHealthService $queue,
        private readonly FailedJobDiagnosticsService $failedJobs,
        private readonly SchedulerHealthService $scheduler,
        private readonly TenantRuntimeProbeService $tenantProbe,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $health = $this->health->overview();
        $queue = $this->queue->summary();
        $failed = $this->failedJobs->summary();
        $scheduler = $this->scheduler->summary();

        $anomalyByCategory = ObservabilityAnomalyEvent::query()
            ->open()
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category')
            ->all();

        return [
            'application_health' => $health['status'],
            'application_reason_codes' => $health['reason_codes'],
            'queue' => [
                'status' => $queue['status'],
                'pending_jobs' => $queue['metrics']['pending_jobs'] ?? 0,
                'failed_jobs' => $queue['metrics']['failed_jobs'] ?? 0,
            ],
            'failed_jobs_by_reason' => array_map(
                fn (array $g) => ['job_label' => $g['job_label'], 'count' => $g['count']],
                $failed['groups'] ?? [],
            ),
            'scheduler_status' => $scheduler['status'],
            'open_anomalies_total' => array_sum($anomalyByCategory),
            'open_anomalies_by_category' => $anomalyByCategory,
            'sync_failure_anomalies' => (int) ObservabilityAnomalyEvent::query()->open()->where('category', ObservabilityAnomalyEvent::CATEGORY_ANDROID_SYNC)->count(),
            'payment_webhook_invalid_anomalies' => (int) ObservabilityAnomalyEvent::query()->open()->where('anomaly_key', 'payment.webhook_rejected_spike')->count(),
            'onboarding_failed_anomalies' => (int) ObservabilityAnomalyEvent::query()->open()->where('category', ObservabilityAnomalyEvent::CATEGORY_ONBOARDING)->count(),
            'degraded_tenants' => $this->tenantProbe->degradedCount(),
            'open_alert_suggestions' => (int) ObservabilityAlertSuggestion::query()->where('status', ObservabilityAlertSuggestion::STATUS_SUGGESTED)->count(),
            'open_support_incidents' => $this->openSupportIncidents(),
        ];
    }

    private function openSupportIncidents(): int
    {
        if (! class_exists(TenantSupportIncident::class)) {
            return 0;
        }

        return (int) TenantSupportIncident::query()
            ->whereNotIn('status', [
                TenantSupportIncident::STATUS_RESOLVED,
                TenantSupportIncident::STATUS_CLOSED,
                TenantSupportIncident::STATUS_CANCELLED,
            ])
            ->count();
    }
}
