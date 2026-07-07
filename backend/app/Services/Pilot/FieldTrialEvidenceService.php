<?php

namespace App\Services\Pilot;

/**
 * Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.
 *
 * Summarises field trial evidence into a GO / WATCH / NO-GO signal. It provides
 * the canonical field trial evidence categories and, when a structured field
 * trial result is supplied (or found on disk), counts open field issues by
 * severity.
 *
 * Gating:
 *   NO-GO — any BLOCKER/CRITICAL field issue still open.
 *   WATCH — any MAJOR field issue still open.
 *   GO    — no blocking or watch issues open (a fresh field trial with no
 *           recorded issues is foundation-ready and reports GO).
 *
 * No secret values or real customer data are ever read into the summary.
 */
class FieldTrialEvidenceService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    /**
     * Canonical evidence category map (key => label).
     *
     * @return array<string,string>
     */
    public function canonicalCategories(): array
    {
        return (array) config('pilot_deployment.evidence_categories', []);
    }

    /**
     * @return array<int,string>
     */
    public function requiredCategories(): array
    {
        return array_values((array) config('pilot_deployment.required_evidence_categories', []));
    }

    /**
     * Evaluate field trial evidence. When $result is empty the method attempts
     * to load a structured result file (config
     * pilot_deployment.field_trial_result_file); if none exists every category
     * is reported present with zero issues (foundation-ready).
     *
     * $result shape (all optional):
     *   [
     *     'issues' => [['severity' => 'BLOCKER', 'status' => 'OPEN'], ...],
     *   ]
     *
     * @param array<string,mixed> $result
     * @return array{
     *   total_categories:int,
     *   required_categories:int,
     *   blocking_issues:int,
     *   watch_issues:int,
     *   categories:array<int,array{key:string,label:string}>,
     *   decision:string
     * }
     */
    public function evaluate(array $result = []): array
    {
        if ($result === []) {
            $result = $this->loadResultFile();
        }

        $issues = (array) ($result['issues'] ?? []);

        $categories = [];
        foreach ($this->canonicalCategories() as $key => $label) {
            $categories[] = ['key' => $key, 'label' => $label];
        }

        $blocking = $this->countIssues($issues, (array) config('pilot_deployment.blocking_issue_severities', []));
        $watch = $this->countIssues($issues, (array) config('pilot_deployment.watch_issue_severities', []));

        return [
            'total_categories' => count($categories),
            'required_categories' => count($this->requiredCategories()),
            'blocking_issues' => $blocking,
            'watch_issues' => $watch,
            'categories' => $categories,
            'decision' => $this->decision($blocking, $watch),
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
     * @param array<int,string> $severities
     */
    private function countIssues(array $issues, array $severities): int
    {
        $openStatuses = array_map('strtoupper', (array) config('pilot_deployment.open_issue_statuses', []));
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

    /**
     * @return array<string,mixed>
     */
    private function loadResultFile(): array
    {
        $relative = (string) config('pilot_deployment.field_trial_result_file', '');
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
