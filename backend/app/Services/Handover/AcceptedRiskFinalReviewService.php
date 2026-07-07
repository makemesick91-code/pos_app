<?php

namespace App\Services\Handover;

use App\Models\PilotDefect;
use Illuminate\Support\Carbon;

/**
 * Sprint 18 — final accepted-risk review for pilot closure / production handover.
 *
 * Aggregates every defect under ACCEPTED_RISK status and evaluates whether each
 * acceptance is still valid: an approver, a reason, and (for blocking/major
 * severities) an expiry/review date are required, and the acceptance must not be
 * past its expiry. The ORIGINAL severity is always preserved in the output —
 * accepted risk must never silently hide how severe a defect really is.
 *
 * Decision:
 *   NO_GO — an expired acceptance on a blocking-severity defect.
 *   WATCH — any valid accepted risk, an incomplete acceptance, or a non-blocking
 *           expired acceptance.
 *   GO    — no accepted-risk defects.
 */
class AcceptedRiskFinalReviewService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string,mixed>
     */
    public function review(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $blocking = (array) config('production_handover.blocking_defect_severities', []);
        $requiresExpiry = (array) config('pilot_stabilization.accepted_risk_requires_expiry_for', []);

        $items = [];
        $valid = 0;
        $expired = 0;
        $expiredBlocking = 0;
        $incomplete = 0;

        $accepted = PilotDefect::query()
            ->where('status', PilotDefect::STATUS_ACCEPTED_RISK)
            ->get();

        foreach ($accepted as $defect) {
            $isBlocking = in_array($defect->severity, $blocking, true);
            $isExpired = $defect->accepted_risk_at !== null
                && $defect->accepted_risk_expires_at !== null
                && $defect->accepted_risk_expires_at->lessThanOrEqualTo($now);

            $missingApprover = $defect->accepted_risk_by === null;
            $missingReason = trim((string) $defect->accepted_risk_reason) === '';
            $missingExpiry = $defect->accepted_risk_expires_at === null
                && in_array($defect->severity, $requiresExpiry, true);
            $isIncomplete = $missingApprover || $missingReason || $missingExpiry;

            if ($isExpired && $isBlocking) {
                $expiredBlocking++;
            } elseif ($isExpired) {
                $expired++;
            } elseif ($isIncomplete) {
                $incomplete++;
            } else {
                $valid++;
            }

            $items[] = [
                'id' => $defect->id,
                'defect_reference' => $defect->defect_reference,
                'original_severity' => $defect->severity, // never hidden
                'blocking' => (bool) $defect->blocking,
                'accepted_risk_by' => $defect->accepted_risk_by,
                'expires_at' => optional($defect->accepted_risk_expires_at)->toIso8601String(),
                'expired' => $isExpired,
                'incomplete' => $isIncomplete,
                'missing' => array_values(array_filter([
                    $missingApprover ? 'approver' : null,
                    $missingReason ? 'reason' : null,
                    $missingExpiry ? 'expiry' : null,
                ])),
            ];
        }

        return [
            'decision' => $this->decide($expiredBlocking, $expired, $incomplete, $valid),
            'counts' => [
                'total' => $accepted->count(),
                'valid' => $valid,
                'expired' => $expired,
                'expired_blocking' => $expiredBlocking,
                'incomplete' => $incomplete,
            ],
            'items' => $items,
        ];
    }

    private function decide(int $expiredBlocking, int $expired, int $incomplete, int $valid): string
    {
        if ($expiredBlocking > 0) {
            return self::DECISION_NO_GO;
        }

        if ($expired > 0 || $incomplete > 0 || $valid > 0) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }
}
