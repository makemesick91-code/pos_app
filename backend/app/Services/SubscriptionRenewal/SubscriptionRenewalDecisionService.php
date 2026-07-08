<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalActivity;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalDecision;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sprint 24 — subscription renewal decision lifecycle.
 *
 * Records a manual renewal decision (approve / approve-with-risk / reject / defer
 * / do-not-renew). Recording a decision NEVER mutates a TenantSubscription and
 * payment evidence NEVER triggers a renewal.
 *
 * The ONLY subscription-mutating path is applyManualRenewalDecision(), which is
 * explicit, requires a decider and effective dates, is limited to a RECORDED
 * APPROVE_MANUAL_RENEWAL / APPROVE_WITH_RISK decision, and is invoked only from a
 * platform-admin, audit-logged endpoint. It extends the subscription period only;
 * it never touches the payment gateway, the plan, or the device limit.
 */
class SubscriptionRenewalDecisionService
{
    use SanitizesSubscriptionRenewalText;

    public function __construct(
        private readonly SubscriptionRenewalActivityService $activities,
    ) {}

    /**
     * @param array<string,mixed> $attributes
     */
    public function record(SubscriptionRenewalCandidate $candidate, array $attributes, ?User $actor = null): SubscriptionRenewalDecision
    {
        $decision = $this->normalizeDecision((string) ($attributes['decision'] ?? ''));

        $model = SubscriptionRenewalDecision::query()->create([
            'decision_reference' => (string) ($attributes['decision_reference'] ?? $this->generateReference()),
            'candidate_id' => $candidate->id,
            'tenant_id' => $attributes['tenant_id'] ?? $candidate->tenant_id,
            'tenant_subscription_id' => $attributes['tenant_subscription_id'] ?? $candidate->tenant_subscription_id,
            'decision' => $decision,
            'status' => SubscriptionRenewalDecision::STATUS_RECORDED,
            'decided_by_user_id' => $attributes['decided_by_user_id'] ?? $actor?->id,
            'decided_at' => Carbon::now(),
            'effective_start_date' => isset($attributes['effective_start_date']) ? Carbon::parse($attributes['effective_start_date'])->toDateString() : null,
            'effective_end_date' => isset($attributes['effective_end_date']) ? Carbon::parse($attributes['effective_end_date'])->toDateString() : null,
            'approved_plan_id' => $attributes['approved_plan_id'] ?? null,
            'manual_billing_invoice_id' => $attributes['manual_billing_invoice_id'] ?? null,
            'reason' => $this->sanitizeNullableString($attributes['reason'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);

        $this->activities->record([
            'candidate_id' => $candidate->id,
            'tenant_id' => $candidate->tenant_id,
            'tenant_subscription_id' => $candidate->tenant_subscription_id,
            'activity_type' => SubscriptionRenewalActivity::TYPE_MANUAL_RENEWAL_DECISION,
            'status' => SubscriptionRenewalActivity::STATUS_DONE,
            'summary' => "Renewal decision recorded: {$decision}.",
            'completed_at' => Carbon::now(),
        ], $actor);

        return $model;
    }

    public function void(SubscriptionRenewalDecision $decision, ?User $actor = null): SubscriptionRenewalDecision
    {
        $decision->status = SubscriptionRenewalDecision::STATUS_VOIDED;
        $decision->save();

        return $decision->refresh();
    }

    /**
     * Explicit, admin-only manual renewal apply. This is the ONLY method that
     * mutates a TenantSubscription and it is never triggered automatically.
     *
     * Requirements enforced here:
     *  - decision must be RECORDED
     *  - decision must be APPROVE_MANUAL_RENEWAL or APPROVE_WITH_RISK
     *  - a decider (decided_by_user_id) and both effective dates must be present
     *
     * It extends the subscription period and reactivates it. It does NOT change
     * the plan, the device limit, or call any payment gateway.
     */
    public function applyManualRenewalDecision(SubscriptionRenewalDecision $decision, ?User $actor = null): SubscriptionRenewalDecision
    {
        if ($decision->status !== SubscriptionRenewalDecision::STATUS_RECORDED) {
            throw new RuntimeException('Only a RECORDED decision can be manually applied.');
        }

        if (! in_array($decision->decision, SubscriptionRenewalDecision::APPLICABLE_DECISIONS, true)) {
            throw new RuntimeException('Only APPROVE_MANUAL_RENEWAL or APPROVE_WITH_RISK decisions can be applied.');
        }

        if ($decision->decided_by_user_id === null) {
            throw new RuntimeException('Manual apply requires a decider (decided_by_user_id).');
        }

        if ($decision->effective_start_date === null || $decision->effective_end_date === null) {
            throw new RuntimeException('Manual apply requires effective_start_date and effective_end_date.');
        }

        $subscription = $decision->tenant_subscription_id !== null
            ? TenantSubscription::query()->find($decision->tenant_subscription_id)
            : null;

        if ($subscription !== null) {
            // Manual period extension only. Plan and device limit are intentionally
            // left untouched (no auto plan/device-limit change in Sprint 24).
            $subscription->starts_at = Carbon::parse($decision->effective_start_date);
            $subscription->ends_at = Carbon::parse($decision->effective_end_date);
            $subscription->status = TenantSubscription::STATUS_ACTIVE;
            $subscription->save();

            $candidate = SubscriptionRenewalCandidate::query()->find($decision->candidate_id);
            if ($candidate !== null) {
                $candidate->status = SubscriptionRenewalCandidate::STATUS_MANUALLY_RENEWED;
                $candidate->renewal_stage = SubscriptionRenewalCandidate::STAGE_CLOSED;
                $candidate->save();
            }
        }

        $decision->status = SubscriptionRenewalDecision::STATUS_APPLIED_MANUALLY;
        $decision->save();

        return $decision->refresh();
    }

    private function normalizeDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, SubscriptionRenewalDecision::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid renewal decision: {$decision}");
        }

        return $decision;
    }

    private function generateReference(): string
    {
        return 'SRDEC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
