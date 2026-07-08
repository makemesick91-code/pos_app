<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\TenantSubscription;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?SubscriptionPlan $plan = null;

    private function service(): SubscriptionRenewalRunService
    {
        return app(SubscriptionRenewalRunService::class);
    }

    private function sub(array $attrs = []): TenantSubscription
    {
        $this->plan ??= SubscriptionPlan::factory()->create();

        return TenantSubscription::factory()->create(array_merge(['subscription_plan_id' => $this->plan->id], $attrs));
    }

    private function cancelledSub(): TenantSubscription
    {
        $this->plan ??= SubscriptionPlan::factory()->create();

        return TenantSubscription::factory()->cancelled()->create(['subscription_plan_id' => $this->plan->id]);
    }

    public function test_can_create_and_evaluate_run_into_candidates(): void
    {
        // Renewal window subscription (ends in 5 days).
        $sub = $this->sub(['ends_at' => now()->addDays(5)]);

        $run = $this->service()->create([]);
        $run = $this->service()->evaluate($run);

        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $sub->refresh()->status, 'evaluation must not change subscription status');
        $this->assertSame('COMPLETED', $run->status);

        $candidate = SubscriptionRenewalCandidate::query()->where('tenant_subscription_id', $sub->id)->first();
        $this->assertNotNull($candidate);
        $this->assertSame(SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW, $candidate->renewal_stage);
        $this->assertNotNull($candidate->days_until_expiry);
    }

    public function test_stage_and_priority_reflect_expiry(): void
    {
        $graceSub = $this->sub(['ends_at' => now()->subDays(2)]); // within grace (7)
        $overdueSub = $this->sub(['ends_at' => now()->subDays(60)]); // beyond grace

        $run = $this->service()->create([]);
        $this->service()->evaluate($run);

        $grace = SubscriptionRenewalCandidate::query()->where('tenant_subscription_id', $graceSub->id)->first();
        $overdue = SubscriptionRenewalCandidate::query()->where('tenant_subscription_id', $overdueSub->id)->first();

        $this->assertSame(SubscriptionRenewalCandidate::STAGE_GRACE_PERIOD, $grace->renewal_stage);
        $this->assertSame(SubscriptionRenewalCandidate::PRIORITY_HIGH, $grace->priority);
        $this->assertSame(SubscriptionRenewalCandidate::STAGE_OVERDUE, $overdue->renewal_stage);
        $this->assertSame(SubscriptionRenewalCandidate::PRIORITY_URGENT, $overdue->priority);
    }

    public function test_run_does_not_auto_renew_charge_or_suspend(): void
    {
        $sub = $this->sub(['ends_at' => now()->subDays(1)]);
        $originalEnds = $sub->ends_at->toDateTimeString();

        $run = $this->service()->create([]);
        $run = $this->service()->evaluate($run);

        $sub->refresh();
        $this->assertSame($originalEnds, $sub->ends_at->toDateTimeString(), 'run must not extend subscription');
        $this->assertNotSame(TenantSubscription::STATUS_SUSPENDED, $sub->status);
        $this->assertSame(0, (int) $run->summary['auto_renewed']);
        $this->assertSame(0, (int) $run->summary['auto_charged']);
        $this->assertSame(0, (int) $run->summary['auto_suspended']);
    }

    public function test_cancelled_subscription_is_not_evaluated(): void
    {
        $cancelled = $this->cancelledSub();

        $run = $this->service()->create([]);
        $this->service()->evaluate($run);

        $this->assertSame(
            0,
            SubscriptionRenewalCandidate::query()->where('tenant_subscription_id', $cancelled->id)->count(),
            'cancelled subscription must not become a renewal candidate',
        );
    }
}
