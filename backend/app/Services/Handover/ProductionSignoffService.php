<?php

namespace App\Services\Handover;

use App\Models\ProductionHandoverPackage;
use App\Models\ProductionHandoverSignoff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 18 — production handover sign-off governance.
 *
 * Sign-off records are append-only: adding one never mutates or deletes a prior
 * record. Each record validates its signer role and decision. The summary
 * aggregates the LATEST decision per required role:
 *   NO_GO — any REJECTED sign-off.
 *   WATCH — any APPROVED_WITH_RISK sign-off, or a required role not yet approved.
 *   GO    — every required role has an APPROVED / APPROVED_WITH_RISK decision with
 *           none rejected (APPROVED_WITH_RISK still tips the summary to WATCH).
 */
class ProductionSignoffService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $data
     */
    public function addSignoff(ProductionHandoverPackage $package, array $data, ?User $actor = null): ProductionHandoverSignoff
    {
        $role = $this->normalizeRole((string) ($data['signer_role'] ?? ''));
        $decision = $this->normalizeDecision((string) ($data['decision'] ?? ''));

        return $package->signoffs()->create([
            'signoff_reference' => (string) ($data['signoff_reference'] ?? $this->generateReference()),
            'signer_user_id' => $data['signer_user_id'] ?? $actor?->id,
            'signer_name' => $data['signer_name'] ?? $actor?->name,
            'signer_role' => $role,
            'decision' => $decision,
            'notes' => $data['notes'] ?? null,
            'evidence_reference' => $data['evidence_reference'] ?? null,
            'signed_at' => Carbon::now(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(ProductionHandoverPackage $package): array
    {
        $requiredRoles = array_map('strtoupper', (array) config('production_handover.required_signoff_roles', []));
        $watchDecisions = (array) config('production_handover.watch_signoff_decisions', []);
        $blockingDecisions = (array) config('production_handover.blocking_signoff_decisions', []);

        // Latest decision per role (append-only history; the newest wins).
        $latestByRole = [];
        foreach ($package->signoffs()->orderBy('id')->get() as $signoff) {
            $latestByRole[$signoff->signer_role] = $signoff->decision;
        }

        $approved = 0;
        $rejected = 0;
        $approvedWithRisk = 0;
        $missingRoles = [];

        foreach ($requiredRoles as $role) {
            $decision = $latestByRole[$role] ?? null;
            if ($decision === null || $decision === ProductionHandoverSignoff::DECISION_PENDING) {
                $missingRoles[] = $role;
                continue;
            }
            if (in_array($decision, $blockingDecisions, true)) {
                $rejected++;
            } elseif (in_array($decision, $watchDecisions, true)) {
                $approvedWithRisk++;
                $approved++;
            } else {
                $approved++;
            }
        }

        // Any rejected sign-off (even from a non-required role) forces NO_GO.
        $anyRejected = in_array(ProductionHandoverSignoff::DECISION_REJECTED, $latestByRole, true);

        return [
            'decision' => $this->decide($anyRejected || $rejected > 0, $approvedWithRisk, $missingRoles),
            'required_roles' => $requiredRoles,
            'required_count' => count($requiredRoles),
            'approved' => $approved,
            'rejected' => $rejected,
            'approved_with_risk' => $approvedWithRisk,
            'missing_roles' => array_values($missingRoles),
            'total_signoffs' => $package->signoffs()->count(),
        ];
    }

    /**
     * @param array<int,string> $missingRoles
     */
    private function decide(bool $rejected, int $approvedWithRisk, array $missingRoles): string
    {
        if ($rejected) {
            return self::DECISION_NO_GO;
        }

        if ($approvedWithRisk > 0 || $missingRoles !== []) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    private function normalizeRole(string $role): string
    {
        $role = strtoupper(trim($role));
        if (! in_array($role, ProductionHandoverSignoff::ROLES, true)) {
            throw new InvalidArgumentException("Invalid signer role: {$role}");
        }

        return $role;
    }

    private function normalizeDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, ProductionHandoverSignoff::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid sign-off decision: {$decision}");
        }

        return $decision;
    }

    private function generateReference(): string
    {
        return 'SGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
