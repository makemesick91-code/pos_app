<?php

namespace App\Console\Commands;

use App\Services\UsageLedgerAnomaly\UsageLedgerAnomalySummary;
use Illuminate\Console\Command;

/**
 * Sprint 28 — usage-ledger:anomaly-scan. Read-only, redacted anomaly scan of the
 * append-only usage event ledger (ULR-R001, ULR-R002, ULR-R006). Never mutates
 * the ledger, never prints secret values. Exit code: 0 when there are no critical
 * anomalies (or --allow-critical), 1 otherwise.
 */
class UsageLedgerAnomalyScanCommand extends Command
{
    protected $signature = 'usage-ledger:anomaly-scan '
        .'{--tenant= : Restrict to a tenant id} '
        .'{--meter= : Restrict to a meter key} '
        .'{--severity= : Restrict to critical|warning|info} '
        .'{--allow-critical : Exit 0 even if critical anomalies exist} '
        .'{--json : Output JSON}';

    protected $description = 'Read-only, redacted anomaly scan of the usage event ledger.';

    public function handle(UsageLedgerAnomalySummary $summary): int
    {
        $report = $summary->summarize(
            $this->tenantOption(),
            $this->stringOption('meter'),
            $this->stringOption('severity'),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Usage Ledger Anomaly Scan');
            $this->line("total={$report['total']} critical={$report['critical']} warning={$report['warning']} info={$report['info']}");
            $this->line("auto_repairable={$report['auto_repairable']} manual_review={$report['manual_review']}");
            foreach ($report['anomalies'] as $a) {
                $this->line("[{$a['severity']}] {$a['type']} tenant=".($a['tenant_id'] ?? '-').' — '.$a['summary']);
            }
        }

        if ($report['critical'] > 0 && ! $this->option('allow-critical')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function tenantOption(): ?int
    {
        $v = $this->option('tenant');

        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function stringOption(string $key): ?string
    {
        $v = $this->option($key);

        return ($v === null || $v === '') ? null : (string) $v;
    }
}
