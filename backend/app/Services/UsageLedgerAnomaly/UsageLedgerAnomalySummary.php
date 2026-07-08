<?php

namespace App\Services\UsageLedgerAnomaly;

/**
 * Sprint 28 — aggregates a redacted anomaly scan into counts by severity and type
 * plus the (already redacted) anomaly list, for the CLI and the platform-admin
 * read-only API (ULR-R006, ULR-R012). Read-only; never mutates the ledger.
 */
class UsageLedgerAnomalySummary
{
    public function __construct(
        private readonly UsageLedgerAnomalyDetector $detector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(?int $tenantId = null, ?string $meterKey = null, ?string $severity = null): array
    {
        $anomalies = $this->detector->scan($tenantId, $meterKey, $severity);

        $bySeverity = array_fill_keys(UsageLedgerAnomalySeverity::all(), 0);
        $byType = [];
        $autoRepairable = 0;

        foreach ($anomalies as $anomaly) {
            $bySeverity[$anomaly->severity] = ($bySeverity[$anomaly->severity] ?? 0) + 1;
            $byType[$anomaly->type] = ($byType[$anomaly->type] ?? 0) + 1;
            if ($anomaly->autoRepairable) {
                $autoRepairable++;
            }
        }

        return [
            'total' => count($anomalies),
            'critical' => $bySeverity[UsageLedgerAnomalySeverity::CRITICAL] ?? 0,
            'warning' => $bySeverity[UsageLedgerAnomalySeverity::WARNING] ?? 0,
            'info' => $bySeverity[UsageLedgerAnomalySeverity::INFO] ?? 0,
            'auto_repairable' => $autoRepairable,
            'manual_review' => count($anomalies) - $autoRepairable,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'anomalies' => array_map(fn (UsageLedgerAnomaly $a) => $a->toArray(), $anomalies),
        ];
    }

    public function hasCritical(?int $tenantId = null, ?string $meterKey = null): bool
    {
        return ($this->summarize($tenantId, $meterKey)['critical'] ?? 0) > 0;
    }
}
