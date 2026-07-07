<?php

namespace App\Services\Pilot;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Sprint 17 — accepted-risk governance.
 *
 * A defect may be accepted as a known risk (moved to ACCEPTED_RISK status), but:
 *   - an approver is required;
 *   - a reason is required;
 *   - BLOCKER/CRITICAL/MAJOR require an expiry/review date;
 *   - the ORIGINAL severity and blocking flag are preserved (accepted risk must
 *     never hide how severe the defect really is).
 *
 * Accepted risk normally downgrades a NO-GO to WATCH for gating (config
 * pilot_stabilization.accepted_risk_downgrades_blocking_to_watch) — it never
 * silently flips a critical defect to GO.
 */
class AcceptedRiskGovernanceService
{
    public function __construct(private readonly PilotDefectService $defects) {}

    /**
     * @param array<string,mixed> $data expects: reason (string), approver (User|int),
     *                                   expires_at (date|null), evidence_reference (string|null)
     */
    public function accept(PilotDefect $defect, array $data, ?User $actor = null): PilotDefect
    {
        $approverId = $this->resolveApprover($data['approver'] ?? null, $actor);
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw new InvalidArgumentException('Accepted risk requires a reason.');
        }

        $expiresAt = isset($data['expires_at']) && $data['expires_at'] !== null
            ? Carbon::parse((string) $data['expires_at'])
            : null;

        if ($this->requiresExpiry($defect->severity) && $expiresAt === null) {
            throw new InvalidArgumentException(
                "Accepted risk for {$defect->severity} requires an expiry/review date.",
            );
        }

        // Preserve original severity + blocking flag; only the status and the
        // accepted-risk governance fields change.
        $defect->accepted_risk_at = Carbon::now();
        $defect->accepted_risk_by = $approverId;
        $defect->accepted_risk_reason = $reason;
        $defect->accepted_risk_expires_at = $expiresAt;
        $defect->status = PilotDefect::STATUS_ACCEPTED_RISK;
        $defect->save();

        $this->defects->appendEvent($defect, PilotDefectEvent::TYPE_ACCEPTED_RISK, $actor, [
            'to_status' => PilotDefect::STATUS_ACCEPTED_RISK,
            'message' => 'Risk accepted (original severity preserved: '.$defect->severity.').',
            'payload' => [
                'approver_id' => $approverId,
                'reason' => $reason,
                'expires_at' => optional($expiresAt)->toIso8601String(),
                'preserved_severity' => $defect->severity,
                'preserved_blocking' => $defect->blocking,
            ],
            'evidence_reference' => $data['evidence_reference'] ?? null,
        ]);

        return $defect->refresh();
    }

    public function requiresExpiry(string $severity): bool
    {
        return in_array(
            strtoupper($severity),
            (array) config('pilot_stabilization.accepted_risk_requires_expiry_for', []),
            true,
        );
    }

    private function resolveApprover(mixed $approver, ?User $actor): int
    {
        if ($approver instanceof User) {
            return $approver->id;
        }

        if (is_int($approver) || (is_string($approver) && ctype_digit($approver))) {
            return (int) $approver;
        }

        if ($actor !== null) {
            return $actor->id;
        }

        throw new InvalidArgumentException('Accepted risk requires an approver.');
    }
}
