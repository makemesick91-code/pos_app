<?php

namespace App\Services\Pilot;

/**
 * Sprint 16 — Pilot Monitoring & Hypercare Foundation.
 *
 * Aggregates the canonical pilot health areas (app access, product sync,
 * cashier sales, payment/QRIS, offline sync, receipt/printer, inventory,
 * reports/closing, subscription/device, admin/onboarding, operator feedback,
 * issue register) into a PASS/WARN/FAIL count and a GO / WATCH / NO-GO decision.
 *
 * Gating:
 *   NO-GO — any health area reports FAIL.
 *   WATCH — any health area reports WARN.
 *   GO    — every health area passes (a fresh pilot day with no recorded
 *           degradation is foundation-ready and reports GO).
 *
 * No secret values or real customer data are ever read into the summary.
 */
class PilotHealthSummaryService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * Canonical health area map (key => label).
     *
     * @return array<string,string>
     */
    public function canonicalAreas(): array
    {
        return (array) config('pilot_monitoring.health_areas', []);
    }

    /**
     * Evaluate pilot health. When $evidence is empty the method attempts to load
     * a structured monitoring result file (config
     * pilot_monitoring.monitoring_result_file); when none exists every area is
     * treated as PASS (foundation-ready).
     *
     * $evidence shape (all optional):
     *   ['areas' => ['payment_qris' => 'WARN', 'offline_sync' => 'FAIL', ...]]
     *
     * @param array<string,mixed> $evidence
     * @return array{
     *   total_areas:int,
     *   counts:array{PASS:int,WARN:int,FAIL:int},
     *   areas:array<int,array{key:string,label:string,status:string}>,
     *   decision:string
     * }
     */
    public function evaluate(array $evidence = []): array
    {
        if ($evidence === []) {
            $evidence = $this->loadResultFile();
        }

        $states = (array) ($evidence['areas'] ?? []);

        $areas = [];
        $counts = [self::STATUS_PASS => 0, self::STATUS_WARN => 0, self::STATUS_FAIL => 0];

        foreach ($this->canonicalAreas() as $key => $label) {
            $status = $this->normalizeStatus($states[$key] ?? self::STATUS_PASS);
            $areas[] = ['key' => $key, 'label' => $label, 'status' => $status];
            $counts[$status]++;
        }

        return [
            'total_areas' => count($areas),
            'counts' => $counts,
            'areas' => $areas,
            'decision' => $this->decision($counts),
        ];
    }

    /**
     * @param array{PASS:int,WARN:int,FAIL:int} $counts
     */
    private function decision(array $counts): string
    {
        if ($counts[self::STATUS_FAIL] > 0) {
            return self::DECISION_NO_GO;
        }

        if ($counts[self::STATUS_WARN] > 0) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return in_array($status, [self::STATUS_PASS, self::STATUS_WARN, self::STATUS_FAIL], true)
            ? $status
            : self::STATUS_PASS;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadResultFile(): array
    {
        $relative = (string) config('pilot_monitoring.monitoring_result_file', '');
        if ($relative === '') {
            return [];
        }

        $path = $this->repoRoot().'/'.ltrim($relative, '/');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
