<?php

namespace App\Services\Pilot;

/**
 * Sprint 16 — Pilot Monitoring & Hypercare Foundation.
 *
 * Classifies hypercare field issues by severity and turns the open-issue picture
 * into a GO / WATCH / NO-GO decision. It exposes the canonical severity levels
 * and SLA targets and, when a structured issue snapshot is supplied (or found on
 * disk), counts open issues by severity.
 *
 * Gating:
 *   NO-GO — any BLOCKER/CRITICAL issue still open.
 *   WATCH — any MAJOR issue still open.
 *   GO    — only MINOR/TRIVIAL (or no) open issues; ACCEPTED_RISK / CLOSED /
 *           FIXED issues do not count as open.
 *
 * No secret values or real customer data are ever read into the summary.
 */
class HypercareIssueTriageService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    /**
     * Canonical severity level map (level => meaning).
     *
     * @return array<string,string>
     */
    public function severityLevels(): array
    {
        return (array) config('pilot_monitoring.severity_levels', []);
    }

    /**
     * Canonical SLA target map (severity => initial response target).
     *
     * @return array<string,string>
     */
    public function slaTargets(): array
    {
        return (array) config('pilot_monitoring.sla_targets', []);
    }

    /**
     * Evaluate hypercare issues. When $result is empty the method attempts to
     * load a structured issue snapshot file (config
     * pilot_monitoring.issue_result_file); when none exists there are zero open
     * issues (foundation-ready) and the decision is GO.
     *
     * $result shape (all optional):
     *   ['issues' => [['severity' => 'BLOCKER', 'status' => 'OPEN'], ...]]
     *
     * @param array<string,mixed> $result
     * @return array{
     *   severity_levels:array<string,string>,
     *   open_blocker:int,
     *   open_critical:int,
     *   open_major:int,
     *   open_minor:int,
     *   open_trivial:int,
     *   blocking_issues:int,
     *   watch_issues:int,
     *   decision:string
     * }
     */
    public function evaluate(array $result = []): array
    {
        if ($result === []) {
            $result = $this->loadResultFile();
        }

        $issues = (array) ($result['issues'] ?? []);

        $openBlocker = $this->countBySeverity($issues, 'BLOCKER');
        $openCritical = $this->countBySeverity($issues, 'CRITICAL');
        $openMajor = $this->countBySeverity($issues, 'MAJOR');
        $openMinor = $this->countBySeverity($issues, 'MINOR');
        $openTrivial = $this->countBySeverity($issues, 'TRIVIAL');

        $blocking = $openBlocker + $openCritical;

        return [
            'severity_levels' => $this->severityLevels(),
            'open_blocker' => $openBlocker,
            'open_critical' => $openCritical,
            'open_major' => $openMajor,
            'open_minor' => $openMinor,
            'open_trivial' => $openTrivial,
            'blocking_issues' => $blocking,
            'watch_issues' => $openMajor,
            'decision' => $this->decision($blocking, $openMajor),
        ];
    }

    private function decision(int $blocking, int $watch): string
    {
        if ($blocking > 0) {
            return self::DECISION_NO_GO;
        }

        if ($watch > 0) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /**
     * @param array<int,mixed> $issues
     */
    private function countBySeverity(array $issues, string $severity): int
    {
        $openStatuses = array_map('strtoupper', (array) config('pilot_monitoring.open_issue_statuses', []));
        $severity = strtoupper($severity);
        $count = 0;

        foreach ($issues as $issue) {
            $issueSeverity = strtoupper((string) (is_array($issue) ? ($issue['severity'] ?? '') : ''));
            $status = strtoupper((string) (is_array($issue) ? ($issue['status'] ?? 'OPEN') : 'OPEN'));

            if ($issueSeverity === $severity && in_array($status, $openStatuses, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadResultFile(): array
    {
        $relative = (string) config('pilot_monitoring.issue_result_file', '');
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
