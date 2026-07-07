<?php

namespace App\Services\Handover;

use App\Models\PilotClosureRun;
use App\Models\User;
use App\Services\Pilot\DefectBurnDownService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sprint 18 — pilot closure orchestration.
 *
 * Builds a pilot closure evaluation from the final defect review, the final
 * accepted-risk review, and the Sprint 17 stabilization burn-down, produces a
 * GO / WATCH / NO_GO closure decision and checklist, and persists it as a
 * PilotClosureRun. Approve/block record the human decision (append-only actors;
 * evidence is never deleted). Output is aggregate only — never secrets or raw
 * sensitive data.
 */
class PilotClosureService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly FinalDefectReviewService $defectReview,
        private readonly AcceptedRiskFinalReviewService $riskReview,
        private readonly DefectBurnDownService $burnDown,
    ) {}

    /**
     * Produce a fresh (unpersisted) closure evaluation.
     *
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $defect = $this->defectReview->review($now);
        $risk = $this->riskReview->review($now);
        $burnDown = $this->burnDown->summary($now);

        $stabilizationDecision = $this->normalizeBurnDown((string) ($burnDown['decision'] ?? DefectBurnDownService::DECISION_GO));

        $decision = $this->worst([
            $defect['decision'],
            $risk['decision'],
            $stabilizationDecision,
        ]);

        $checklist = [
            'final_defect_review' => $defect['decision'] === self::DECISION_NO_GO ? 'FAIL' : ($defect['decision'] === self::DECISION_WATCH ? 'WARN' : 'PASS'),
            'accepted_risk_review' => $risk['decision'] === self::DECISION_NO_GO ? 'FAIL' : ($risk['decision'] === self::DECISION_WATCH ? 'WARN' : 'PASS'),
            'stabilization_gate' => $stabilizationDecision === self::DECISION_NO_GO ? 'FAIL' : ($stabilizationDecision === self::DECISION_WATCH ? 'WARN' : 'PASS'),
        ];

        return [
            'decision' => $decision,
            'checklist' => $checklist,
            'final_defect_summary' => $defect,
            'accepted_risk_summary' => $risk,
            'handover_readiness_summary' => [
                'stabilization_decision' => $stabilizationDecision,
                'open_blocking' => $defect['counts']['open_blocking'] ?? 0,
                'accepted_risk_expired_blocking' => $risk['counts']['expired_blocking'] ?? 0,
            ],
        ];
    }

    /**
     * Create and persist a closure run from the current evaluation.
     *
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null, ?Carbon $now = null): PilotClosureRun
    {
        $evaluation = $this->evaluate($now);

        return PilotClosureRun::query()->create([
            'closure_reference' => (string) ($attributes['closure_reference'] ?? $this->generateReference()),
            'status' => PilotClosureRun::STATUS_REVIEW,
            'decision' => $evaluation['decision'],
            'window_start' => $attributes['window_start'] ?? null,
            'window_end' => $attributes['window_end'] ?? null,
            'final_defect_summary' => $evaluation['final_defect_summary'],
            'accepted_risk_summary' => $evaluation['accepted_risk_summary'],
            'handover_readiness_summary' => $evaluation['handover_readiness_summary'],
            'checklist' => $evaluation['checklist'],
            'evidence_references' => $attributes['evidence_references'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function approve(PilotClosureRun $run, ?User $actor = null): PilotClosureRun
    {
        $run->status = PilotClosureRun::STATUS_APPROVED;
        $run->approved_by = $actor?->id;
        $run->approved_at = Carbon::now();
        $run->save();

        return $run->refresh();
    }

    public function block(PilotClosureRun $run, ?User $actor = null): PilotClosureRun
    {
        $run->status = PilotClosureRun::STATUS_BLOCKED;
        $run->save();

        return $run->refresh();
    }

    /**
     * @param array<int,string> $decisions
     */
    private function worst(array $decisions): string
    {
        if (in_array(self::DECISION_NO_GO, $decisions, true)) {
            return self::DECISION_NO_GO;
        }

        if (in_array(self::DECISION_WATCH, $decisions, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    private function normalizeBurnDown(string $decision): string
    {
        return match ($decision) {
            DefectBurnDownService::DECISION_NO_GO => self::DECISION_NO_GO,
            DefectBurnDownService::DECISION_WATCH => self::DECISION_WATCH,
            default => self::DECISION_GO,
        };
    }

    private function generateReference(): string
    {
        return 'CLO-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
