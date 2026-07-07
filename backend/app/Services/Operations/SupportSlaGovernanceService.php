<?php

namespace App\Services\Operations;

use Illuminate\Support\Carbon;

/**
 * Sprint 19 — support/SLA governance check.
 *
 * Verifies the support/SLA operations documentation exists, that the incident
 * SLA target table is defined for every severity, and evaluates the live open
 * incident SLA state (breached blocking incidents fail the check). Produces a
 * GO/WATCH/NO_GO decision.
 *
 * Governance/evidence check only — never sends real alerts, never contacts a
 * real support channel, never prints secrets.
 */
class SupportSlaGovernanceService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    private const GOVERNANCE_DOC = 'docs/operations/support-sla-operations.md';

    public function __construct(
        private readonly ProductionIncidentService $incidents,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $incidentSummary = $this->incidents->summary($now);

        $checks = [
            $this->governanceDocCheck(),
            $this->slaTargetsCheck(),
            $this->handoverDocCheck(),
            $this->openIncidentSlaCheck($incidentSummary),
        ];

        return [
            'decision' => $this->decision($checks),
            'checks' => $checks,
            'incident_summary' => $incidentSummary,
            'sla_targets' => (array) config('production_operations.incident_sla_hours', []),
        ];
    }

    /**
     * @param array<int,array{status:string}> $checks
     */
    private function decision(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_FAIL, $statuses, true)) {
            return self::DECISION_NO_GO;
        }

        if (in_array(self::STATUS_WARN, $statuses, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function check(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function governanceDocCheck(): array
    {
        return $this->docExists(self::GOVERNANCE_DOC)
            ? $this->check('governance_doc', self::STATUS_PASS, 'Support/SLA operations doc present.')
            : $this->check('governance_doc', self::STATUS_FAIL, 'Missing support/SLA operations doc: '.self::GOVERNANCE_DOC);
    }

    private function slaTargetsCheck(): array
    {
        $targets = (array) config('production_operations.incident_sla_hours', []);
        $missing = array_diff(['P0', 'P1', 'P2', 'P3', 'P4'], array_keys($targets));

        return $missing === []
            ? $this->check('sla_targets', self::STATUS_PASS, 'SLA targets defined for all severities.')
            : $this->check('sla_targets', self::STATUS_FAIL, 'SLA targets missing for: '.implode(', ', $missing));
    }

    private function handoverDocCheck(): array
    {
        $doc = 'docs/handover/support-sla-handover.md';

        return $this->docExists($doc)
            ? $this->check('support_handover', self::STATUS_PASS, 'Support/SLA handover present.')
            : $this->check('support_handover', self::STATUS_WARN, 'Missing support/SLA handover: '.$doc);
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function openIncidentSlaCheck(array $summary): array
    {
        $breachedBlocking = (int) ($summary['counts']['sla_breached_blocking'] ?? 0);
        $breached = (int) ($summary['counts']['sla_breached'] ?? 0);

        if ($breachedBlocking > 0) {
            return $this->check('open_incident_sla', self::STATUS_FAIL, "{$breachedBlocking} blocking incident(s) past SLA.");
        }

        if ($breached > 0) {
            return $this->check('open_incident_sla', self::STATUS_WARN, "{$breached} incident(s) past SLA.");
        }

        return $this->check('open_incident_sla', self::STATUS_PASS, 'No SLA-breached open incidents.');
    }

    private function docExists(string $path): bool
    {
        return is_file($this->repoRoot().'/'.ltrim($path, '/'));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
