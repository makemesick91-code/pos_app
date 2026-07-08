<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:device-summary. Safe, redacted summary of device
 * activations. No token hash, fingerprint or PII (ADR-R020).
 */
class AndroidRuntimeDeviceSummaryCommand extends Command
{
    protected $signature = 'android-runtime:device-summary {--tenant= : Scope to a tenant id} {--json : Output JSON}';

    protected $description = 'Show a safe summary of Android device activations.';

    public function handle(AndroidRuntimeSummaryService $summary): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $report = $summary->deviceSummary($tenantId);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Android device activations: '.$report['total']);
        foreach ($report['by_status'] as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}
