<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityAnomalyScanService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:anomaly-scan. Scans Android sync, billing/payment,
 * entitlement, onboarding and export/report anomalies from the Sprint 30–35
 * ledgers. DRY-RUN by default; --execute persists observability anomaly events
 * ONLY (never a domain mutation). --tenant scopes to one tenant. No PII/secrets.
 */
class ObservabilityAnomalyScanCommand extends Command
{
    protected $signature = 'observability:anomaly-scan {--execute : Persist detected anomaly events} {--tenant= : Tenant id or code} {--json : Output JSON}';

    protected $description = 'Scan for observability anomalies (dry-run by default; --execute persists events only).';

    public function handle(ObservabilityAnomalyScanService $scan): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));
        $result = $scan->scan((bool) $this->option('execute'), $tenant?->id);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $mode = $result['executed'] ? 'EXECUTE' : 'DRY-RUN';
        $this->line("Anomaly scan ({$mode}): detected={$result['detected']} persisted={$result['persisted']} updated={$result['updated']}");
        foreach ($result['anomalies'] as $a) {
            $this->line("  [{$a['severity']}] {$a['category']} :: {$a['anomaly_key']} (tenant=".($a['tenant_id'] ?? 'app').')');
        }

        return self::SUCCESS;
    }
}
