<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalActivity;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal candidate lifecycle.
 *
 * Updates candidate assignment/status/stage and records the status transitions
 * (ready-for-manual-renewal, grace review, overdue review, do-not-renew). Every
 * transition writes a manual renewal activity for the audit trail. This service
 * NEVER mutates a TenantSubscription — READY_FOR_MANUAL_RENEWAL only flags that
 * an admin decision is required.
 */
class SubscriptionRenewalCandidateService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';

    public function __construct(
        private readonly SubscriptionRenewalActivityService $activities,
    ) {}

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SubscriptionRenewalCandidate $candidate, array $attributes, ?User $actor = null): SubscriptionRenewalCandidate
    {
        $map = [
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'renewal_stage' => fn ($v) => $this->normalizeStage((string) $v),
            'priority' => fn ($v) => $this->normalizePriority((string) $v),
            'assigned_to_user_id' => fn ($v) => $v,
            'billing_invoice_id' => fn ($v) => $v,
            'billing_account_id' => fn ($v) => $v,
            'last_payment_evidence_status' => fn ($v) => $this->sanitizeNullableString($v),
            'notes' => fn ($v) => $this->sanitizeNullableString($v),
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $candidate->{$key} = $caster($attributes[$key]);
            }
        }

        $candidate->save();

        return $candidate->refresh();
    }

    public function markReadyForManualRenewal(SubscriptionRenewalCandidate $candidate, ?User $actor = null): SubscriptionRenewalCandidate
    {
        return $this->transition(
            $candidate,
            SubscriptionRenewalCandidate::STATUS_READY_FOR_MANUAL_RENEWAL,
            SubscriptionRenewalCandidate::STAGE_MANUAL_REVIEW,
            SubscriptionRenewalActivity::TYPE_RENEWAL_REVIEW,
            'Candidate marked ready for manual renewal (admin decision required).',
            $actor,
            fn ($c) => $c->qualified_for_manual_renewal_at = Carbon::now(),
        );
    }

    public function markGraceReview(SubscriptionRenewalCandidate $candidate, ?User $actor = null): SubscriptionRenewalCandidate
    {
        return $this->transition(
            $candidate,
            SubscriptionRenewalCandidate::STATUS_GRACE_REVIEW,
            SubscriptionRenewalCandidate::STAGE_GRACE_PERIOD,
            SubscriptionRenewalActivity::TYPE_GRACE_REVIEW,
            'Candidate moved to grace review (no auto-suspension).',
            $actor,
        );
    }

    public function markOverdueReview(SubscriptionRenewalCandidate $candidate, ?User $actor = null): SubscriptionRenewalCandidate
    {
        return $this->transition(
            $candidate,
            SubscriptionRenewalCandidate::STATUS_OVERDUE_REVIEW,
            SubscriptionRenewalCandidate::STAGE_OVERDUE,
            SubscriptionRenewalActivity::TYPE_OVERDUE_REVIEW,
            'Candidate moved to overdue review (no auto-suspension).',
            $actor,
        );
    }

    public function markDoNotRenew(SubscriptionRenewalCandidate $candidate, ?User $actor = null): SubscriptionRenewalCandidate
    {
        return $this->transition(
            $candidate,
            SubscriptionRenewalCandidate::STATUS_DO_NOT_RENEW,
            SubscriptionRenewalCandidate::STAGE_CLOSED,
            SubscriptionRenewalActivity::TYPE_RENEWAL_REVIEW,
            'Candidate marked do-not-renew.',
            $actor,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SubscriptionRenewalCandidate::query()->get();

        $byStatus = [];
        foreach (SubscriptionRenewalCandidate::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        $byStage = [];
        foreach (SubscriptionRenewalCandidate::STAGES as $stage) {
            $count = $all->where('renewal_stage', $stage)->count();
            if ($count > 0) {
                $byStage[$stage] = $count;
            }
        }

        $byPriority = [];
        foreach (SubscriptionRenewalCandidate::PRIORITIES as $priority) {
            $count = $all->where('priority', $priority)->count();
            if ($count > 0) {
                $byPriority[$priority] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_candidates' => $all->count(),
            'by_status' => $byStatus,
            'by_stage' => $byStage,
            'by_priority' => $byPriority,
            'renewal_window_count' => $all->where('renewal_stage', SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW)->count(),
            'grace_review_count' => $all->where('status', SubscriptionRenewalCandidate::STATUS_GRACE_REVIEW)->count(),
            'overdue_review_count' => $all->where('status', SubscriptionRenewalCandidate::STATUS_OVERDUE_REVIEW)->count(),
            'ready_for_manual_renewal_count' => $all->where('status', SubscriptionRenewalCandidate::STATUS_READY_FOR_MANUAL_RENEWAL)->count(),
            'auto_subscription_mutation' => false,
        ];
    }

    private function transition(
        SubscriptionRenewalCandidate $candidate,
        string $status,
        string $stage,
        string $activityType,
        string $summary,
        ?User $actor,
        ?callable $mutator = null,
    ): SubscriptionRenewalCandidate {
        $candidate->status = $status;
        $candidate->renewal_stage = $stage;
        if ($mutator !== null) {
            $mutator($candidate);
        }
        $candidate->save();

        $this->activities->record([
            'candidate_id' => $candidate->id,
            'tenant_id' => $candidate->tenant_id,
            'tenant_subscription_id' => $candidate->tenant_subscription_id,
            'activity_type' => $activityType,
            'status' => SubscriptionRenewalActivity::STATUS_DONE,
            'summary' => $summary,
            'completed_at' => Carbon::now(),
        ], $actor);

        return $candidate->refresh();
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SubscriptionRenewalCandidate::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid candidate status: {$status}");
        }

        return $status;
    }

    private function normalizeStage(string $stage): string
    {
        $stage = strtoupper(trim($stage));
        if (! in_array($stage, SubscriptionRenewalCandidate::STAGES, true)) {
            throw new InvalidArgumentException("Invalid renewal stage: {$stage}");
        }

        return $stage;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtoupper(trim($priority));
        if (! in_array($priority, SubscriptionRenewalCandidate::PRIORITIES, true)) {
            throw new InvalidArgumentException("Invalid priority: {$priority}");
        }

        return $priority;
    }
}
