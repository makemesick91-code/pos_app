<?php

namespace App\Services\Pilot;

/**
 * Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.
 *
 * Summarises operator UAT results into a GO / WATCH / NO-GO signal. It provides
 * the canonical scenario list and, when a structured UAT result is supplied
 * (or found on disk), counts scenario statuses and open issues.
 *
 * Gating:
 *   NO-GO — any BLOCKER/CRITICAL issue still open, or any scenario FAIL.
 *   WATCH — any MAJOR issue still open, or any scenario WATCH.
 *   GO    — no blockers, no failing scenarios (pending scenarios are allowed
 *           during the foundation phase and do not force a downgrade).
 *
 * No secret values or real customer data are ever read into the summary.
 */
class OperatorUatSummaryService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WATCH = 'WATCH';
    public const STATUS_FAIL = 'FAIL';
    public const STATUS_PENDING = 'PENDING';

    /**
     * Canonical scenario map (key => label).
     *
     * @return array<string,string>
     */
    public function canonicalScenarios(): array
    {
        return (array) config('pilot_uat.scenarios', []);
    }

    /**
     * @return array<int,string>
     */
    public function requiredScenarios(): array
    {
        return array_values((array) config('pilot_uat.required_scenarios', []));
    }

    /**
     * Evaluate UAT results. When $result is empty the method attempts to load a
     * structured result file (config pilot_uat.uat_result_file); if none exists
     * every scenario is treated as PENDING with zero issues (foundation-ready).
     *
     * $result shape (all optional):
     *   [
     *     'scenarios' => ['login' => 'PASS', 'cash_sale' => 'FAIL', ...],
     *     'issues' => [['severity' => 'BLOCKER', 'status' => 'OPEN'], ...],
     *   ]
     *
     * @param array<string,mixed> $result
     * @return array{
     *   total_scenarios:int,
     *   required_scenarios:int,
     *   blocking_issues:int,
     *   watch_issues:int,
     *   scenarios:array<int,array{key:string,label:string,status:string}>,
     *   decision:string
     * }
     */
    public function evaluate(array $result = []): array
    {
        if ($result === []) {
            $result = $this->loadResultFile();
        }

        $statuses = (array) ($result['scenarios'] ?? []);
        $issues = (array) ($result['issues'] ?? []);

        $scenarios = [];
        foreach ($this->canonicalScenarios() as $key => $label) {
            $scenarios[] = [
                'key' => $key,
                'label' => $label,
                'status' => $this->normalizeStatus($statuses[$key] ?? self::STATUS_PENDING),
            ];
        }

        $blocking = $this->countIssues($issues, (array) config('pilot_uat.blocking_issue_severities', []));
        $watch = $this->countIssues($issues, (array) config('pilot_uat.watch_issue_severities', []));

        return [
            'total_scenarios' => count($scenarios),
            'required_scenarios' => count($this->requiredScenarios()),
            'blocking_issues' => $blocking,
            'watch_issues' => $watch,
            'scenarios' => $scenarios,
            'decision' => $this->decision($scenarios, $blocking, $watch),
        ];
    }

    /**
     * @param array<int,array{key:string,label:string,status:string}> $scenarios
     */
    private function decision(array $scenarios, int $blocking, int $watch): string
    {
        $statuses = array_column($scenarios, 'status');

        if ($blocking > 0 || in_array(self::STATUS_FAIL, $statuses, true)) {
            return self::DECISION_NO_GO;
        }

        if ($watch > 0 || in_array(self::STATUS_WATCH, $statuses, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /**
     * @param array<int,mixed> $issues
     * @param array<int,string> $severities
     */
    private function countIssues(array $issues, array $severities): int
    {
        $openStatuses = array_map('strtoupper', (array) config('pilot_uat.open_issue_statuses', []));
        $severities = array_map('strtoupper', $severities);
        $count = 0;

        foreach ($issues as $issue) {
            $severity = strtoupper((string) (is_array($issue) ? ($issue['severity'] ?? '') : ''));
            $status = strtoupper((string) (is_array($issue) ? ($issue['status'] ?? 'OPEN') : 'OPEN'));

            if (in_array($severity, $severities, true) && in_array($status, $openStatuses, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return in_array($status, [self::STATUS_PASS, self::STATUS_WATCH, self::STATUS_FAIL, self::STATUS_PENDING], true)
            ? $status
            : self::STATUS_PENDING;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadResultFile(): array
    {
        $relative = (string) config('pilot_uat.uat_result_file', '');
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
