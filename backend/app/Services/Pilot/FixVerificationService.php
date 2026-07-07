<?php

namespace App\Services\Pilot;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 17 — fix verification / retest workflow.
 *
 * A defect being FIXED is not the same as CLOSED. The workflow is:
 *   markFixed()       -> FIXED   (fixed_at stamped)
 *   requestRetest()   -> RETEST
 *   verify(pass)      -> VERIFIED (verified_at/by/result, optionally CLOSED)
 *   verify(fail)      -> IN_PROGRESS (result recorded, defect reopened)
 *
 * Each step appends a typed event through PilotDefectService so history is
 * preserved. Verification result is always explicit.
 */
class FixVerificationService
{
    public function __construct(private readonly PilotDefectService $defects) {}

    public function markFixed(PilotDefect $defect, ?User $actor = null, ?string $evidenceReference = null): PilotDefect
    {
        $defect->fixed_at = Carbon::now();
        $defect->save();

        $this->defects->transitionStatus($defect, PilotDefect::STATUS_FIXED, $actor);

        if ($evidenceReference !== null) {
            $this->defects->appendEvent($defect, PilotDefectEvent::TYPE_FIXED, $actor, [
                'message' => 'Fix recorded with evidence.',
                'evidence_reference' => $evidenceReference,
            ]);
        }

        return $defect->refresh();
    }

    public function requestRetest(PilotDefect $defect, ?User $actor = null): PilotDefect
    {
        return $this->defects->transitionStatus($defect, PilotDefect::STATUS_RETEST, $actor);
    }

    /**
     * Record the retest outcome. On PASS the defect becomes VERIFIED (and CLOSED
     * when $close is true). On FAIL it returns to IN_PROGRESS.
     */
    public function verify(
        PilotDefect $defect,
        bool $passed,
        ?User $actor = null,
        ?string $evidenceReference = null,
        bool $close = false,
    ): PilotDefect {
        $result = $passed ? PilotDefect::VERIFICATION_PASS : PilotDefect::VERIFICATION_FAIL;

        $defect->verification_result = $result;
        $defect->verified_by = $actor?->id;
        $defect->verified_at = Carbon::now();
        $defect->save();

        $this->defects->appendEvent($defect, PilotDefectEvent::TYPE_VERIFIED, $actor, [
            'message' => "Retest verification: {$result}.",
            'payload' => ['result' => $result],
            'evidence_reference' => $evidenceReference,
        ]);

        if ($passed) {
            $this->defects->transitionStatus($defect, PilotDefect::STATUS_VERIFIED, $actor);
            if ($close) {
                $this->defects->transitionStatus($defect, PilotDefect::STATUS_CLOSED, $actor);
            }
        } else {
            $this->defects->transitionStatus($defect, PilotDefect::STATUS_IN_PROGRESS, $actor);
        }

        return $defect->refresh();
    }
}
