<?php

namespace App\Services\Handover;

use App\Models\PilotDefect;
use App\Services\Pilot\DefectBurnDownService;
use App\Services\Pilot\SlaBreachDetectionService;
use Illuminate\Support\Carbon;

/**
 * Sprint 18 — final defect review for pilot closure / production handover.
 *
 * Aggregates open defects by severity/status/area, counts unresolved blocking
 * (BLOCKER/CRITICAL) and major defects, SLA-breached open defects, accepted-risk
 * defects, and fix/retest/verify state, and produces a GO / WATCH / NO_GO
 * decision. Reuses the Sprint 17 defect governance (burn-down + SLA) so a single
 * gating contract is preserved — accepted risk changes impact, never severity.
 */
class FinalDefectReviewService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly DefectBurnDownService $burnDown,
        private readonly SlaBreachDetectionService $sla,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function review(?Carbon $now = null): array
    {
        $burnDown = $this->burnDown->summary($now);
        $sla = $this->sla->summary($now);

        $counts = $burnDown['counts'] ?? [];

        return [
            'decision' => $this->normalizeDecision((string) ($burnDown['decision'] ?? DefectBurnDownService::DECISION_GO)),
            'counts' => [
                'total' => $counts['total'] ?? 0,
                'open' => $counts['open'] ?? 0,
                'open_blocking' => $counts['open_blocking'] ?? 0,
                'open_major' => $counts['open_major'] ?? 0,
                'accepted_risk' => $counts['accepted_risk'] ?? 0,
                'accepted_risk_blocking_expired' => $counts['accepted_risk_blocking_expired'] ?? 0,
                'sla_breached_open' => $counts['sla_breached_open'] ?? 0,
                'fixed' => $counts['fixed'] ?? 0,
                'retest' => $counts['retest'] ?? 0,
                'verified' => $counts['verified'] ?? 0,
                'closed' => $counts['closed'] ?? 0,
            ],
            'by_severity' => $burnDown['by_severity'] ?? [],
            'by_status' => $burnDown['by_status'] ?? [],
            'by_area' => $burnDown['by_area'] ?? [],
            'unresolved_blocking' => $this->unresolvedBlocking($now),
            'sla' => [
                'breached_count' => $sla['breached_count'] ?? 0,
            ],
        ];
    }

    /**
     * References (id/reference/severity/status) for open blocking defects without
     * valid accepted risk — the defects that actually force NO_GO. Never exposes
     * free-text (which may contain sanitised-but-still-noisy content).
     *
     * @return array<int,array{id:int,defect_reference:string,severity:string,status:string}>
     */
    private function unresolvedBlocking(?Carbon $now): array
    {
        $blocking = (array) config('production_handover.blocking_defect_severities', []);

        return PilotDefect::query()
            ->open()
            ->whereIn('severity', $blocking)
            ->get()
            ->map(fn (PilotDefect $d) => [
                'id' => $d->id,
                'defect_reference' => $d->defect_reference,
                'severity' => $d->severity,
                'status' => $d->status,
            ])
            ->values()
            ->all();
    }

    private function normalizeDecision(string $decision): string
    {
        return match ($decision) {
            DefectBurnDownService::DECISION_NO_GO => self::DECISION_NO_GO,
            DefectBurnDownService::DECISION_WATCH => self::DECISION_WATCH,
            default => self::DECISION_GO,
        };
    }
}
