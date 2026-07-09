<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityMetricsService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:metrics-summary. Shows safe operational dashboard
 * metrics (aggregate counts only). No raw payloads/PII. --json.
 */
class ObservabilityMetricsSummaryCommand extends Command
{
    protected $signature = 'observability:metrics-summary {--json : Output JSON}';

    protected $description = 'Show safe operational dashboard metrics (aggregate counts only).';

    public function handle(ObservabilityMetricsService $metrics): int
    {
        $summary = $metrics->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Application health: '.$summary['application_health']);
        $this->line('Open anomalies: '.$summary['open_anomalies_total']);
        $this->line('Degraded tenants: '.$summary['degraded_tenants']);
        $this->line('Open alert suggestions: '.$summary['open_alert_suggestions']);
        $this->line('Open support incidents: '.$summary['open_support_incidents']);

        return self::SUCCESS;
    }
}
