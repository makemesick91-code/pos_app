<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalActivity;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\TenantSubscription;
use App\Services\SubscriptionRenewal\SubscriptionRenewalCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?SubscriptionPlan $plan = null;

    private function service(): SubscriptionRenewalCandidateService
    {
        return app(SubscriptionRenewalCandidateService::class);
    }

    private function candidate(): SubscriptionRenewalCandidate
    {
        $this->plan ??= SubscriptionPlan::factory()->create();
        $sub = TenantSubscription::factory()->create(['subscription_plan_id' => $this->plan->id, 'ends_at' => now()->addDays(3)]);

        return SubscriptionRenewalCandidate::query()->create([
            'candidate_reference' => 'CAND-'.uniqid(),
            'tenant_id' => $sub->tenant_id,
            'tenant_subscription_id' => $sub->id,
            'status' => SubscriptionRenewalCandidate::STATUS_NEW,
            'renewal_stage' => SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW,
            'priority' => SubscriptionRenewalCandidate::PRIORITY_NORMAL,
        ]);
    }

    public function test_can_transition_status_and_stage(): void
    {
        $candidate = $this->candidate();
        $updated = $this->service()->update($candidate, ['status' => SubscriptionRenewalCandidate::STATUS_IN_REVIEW]);
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_IN_REVIEW, $updated->status);
    }

    public function test_mark_ready_grace_overdue_do_not_renew_record_activity(): void
    {
        $ready = $this->service()->markReadyForManualRenewal($this->candidate());
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_READY_FOR_MANUAL_RENEWAL, $ready->status);
        $this->assertNotNull($ready->qualified_for_manual_renewal_at);

        $grace = $this->service()->markGraceReview($this->candidate());
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_GRACE_REVIEW, $grace->status);

        $overdue = $this->service()->markOverdueReview($this->candidate());
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_OVERDUE_REVIEW, $overdue->status);

        $dnr = $this->service()->markDoNotRenew($this->candidate());
        $this->assertSame(SubscriptionRenewalCandidate::STATUS_DO_NOT_RENEW, $dnr->status);

        $this->assertGreaterThanOrEqual(4, SubscriptionRenewalActivity::query()->count());
    }

    public function test_transition_does_not_mutate_tenant_subscription(): void
    {
        $candidate = $this->candidate();
        $sub = TenantSubscription::query()->find($candidate->tenant_subscription_id);
        $before = $sub->ends_at->toDateTimeString();

        $this->service()->markReadyForManualRenewal($candidate);

        $this->assertSame($before, $sub->refresh()->ends_at->toDateTimeString());
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $sub->status);
    }

    public function test_summary_counts_by_status(): void
    {
        $this->service()->markReadyForManualRenewal($this->candidate());
        $summary = $this->service()->summary();

        $this->assertSame(1, $summary['ready_for_manual_renewal_count']);
        $this->assertFalse($summary['auto_subscription_mutation']);
    }
}
