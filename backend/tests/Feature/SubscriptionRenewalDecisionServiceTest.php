<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalDecision;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionRenewal\SubscriptionRenewalDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SubscriptionRenewalDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalDecisionService
    {
        return app(SubscriptionRenewalDecisionService::class);
    }

    private function candidate(): SubscriptionRenewalCandidate
    {
        $sub = TenantSubscription::factory()->create(['subscription_plan_id' => SubscriptionPlan::factory()->create()->id, 'ends_at' => now()->addDays(3)]);

        return SubscriptionRenewalCandidate::query()->create([
            'candidate_reference' => 'CAND-'.uniqid(),
            'tenant_id' => $sub->tenant_id,
            'tenant_subscription_id' => $sub->id,
            'status' => SubscriptionRenewalCandidate::STATUS_READY_FOR_MANUAL_RENEWAL,
            'renewal_stage' => SubscriptionRenewalCandidate::STAGE_MANUAL_REVIEW,
            'priority' => SubscriptionRenewalCandidate::PRIORITY_NORMAL,
        ]);
    }

    public function test_recording_a_decision_does_not_mutate_subscription(): void
    {
        $candidate = $this->candidate();
        $sub = TenantSubscription::query()->find($candidate->tenant_subscription_id);
        $before = $sub->ends_at->toDateTimeString();

        $decision = $this->service()->record($candidate, [
            'decision' => SubscriptionRenewalDecision::DECISION_APPROVE_MANUAL_RENEWAL,
        ]);

        $this->assertSame(SubscriptionRenewalDecision::STATUS_RECORDED, $decision->status);
        $this->assertSame($before, $sub->refresh()->ends_at->toDateTimeString());
    }

    public function test_can_record_all_decision_types(): void
    {
        foreach (SubscriptionRenewalDecision::DECISIONS as $type) {
            $decision = $this->service()->record($this->candidate(), ['decision' => $type]);
            $this->assertSame($type, $decision->decision);
        }
    }

    public function test_apply_manual_renewal_extends_subscription_explicitly(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $candidate = $this->candidate();

        $decision = $this->service()->record($candidate, [
            'decision' => SubscriptionRenewalDecision::DECISION_APPROVE_MANUAL_RENEWAL,
            'decided_by_user_id' => $user->id,
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => now()->addMonth()->toDateString(),
        ]);

        $applied = $this->service()->applyManualRenewalDecision($decision, $user);

        $this->assertSame(SubscriptionRenewalDecision::STATUS_APPLIED_MANUALLY, $applied->status);
        $sub = TenantSubscription::query()->find($candidate->tenant_subscription_id);
        $this->assertSame(now()->addMonth()->toDateString(), $sub->ends_at->toDateString());
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_MANUALLY_RENEWED, $candidate->refresh()->status);
    }

    public function test_apply_requires_recorded_applicable_decision(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $decision = $this->service()->record($this->candidate(), [
            'decision' => SubscriptionRenewalDecision::DECISION_REJECT_RENEWAL,
            'decided_by_user_id' => $user->id,
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->applyManualRenewalDecision($decision, $user);
    }

    public function test_apply_requires_decider_and_effective_dates(): void
    {
        $decision = $this->service()->record($this->candidate(), [
            'decision' => SubscriptionRenewalDecision::DECISION_APPROVE_MANUAL_RENEWAL,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->applyManualRenewalDecision($decision, null);
    }

    public function test_apply_does_not_change_plan_or_device_limit(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $candidate = $this->candidate();
        $sub = TenantSubscription::query()->find($candidate->tenant_subscription_id);
        $originalPlan = $sub->subscription_plan_id;

        $decision = $this->service()->record($candidate, [
            'decision' => SubscriptionRenewalDecision::DECISION_APPROVE_WITH_RISK,
            'decided_by_user_id' => $user->id,
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => now()->addMonth()->toDateString(),
            'approved_plan_id' => $originalPlan,
        ]);

        $this->service()->applyManualRenewalDecision($decision, $user);

        $this->assertSame($originalPlan, $sub->refresh()->subscription_plan_id);
    }
}
