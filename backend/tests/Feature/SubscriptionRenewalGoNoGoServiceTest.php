<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalSignoff;
use App\Services\SubscriptionRenewal\SubscriptionRenewalGoNoGoService;
use App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService;
use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalGoNoGoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalGoNoGoService
    {
        return app(SubscriptionRenewalGoNoGoService::class);
    }

    private function approveAllRoles(): void
    {
        $readiness = app(SubscriptionRenewalReadinessService::class);
        foreach ((array) config('subscription_renewal.required_signoff_roles') as $role) {
            $readiness->addSignoff(['signer_role' => $role, 'decision' => SubscriptionRenewalSignoff::DECISION_APPROVED]);
        }
    }

    public function test_aggregates_prior_gates(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('billing_collection_gate', $report['gates']);
        $this->assertArrayHasKey('release_gate', $report['gates']);
        $this->assertTrue($report['gates']['billing_collection_gate']);
    }

    public function test_go_when_prior_gates_and_readiness_pass(): void
    {
        app(SubscriptionRenewalPolicyService::class)->ensureDefault();
        $this->approveAllRoles();

        $this->assertSame('GO', $this->service()->evaluate()['decision']);
    }

    public function test_watch_when_approved_with_risk(): void
    {
        app(SubscriptionRenewalPolicyService::class)->ensureDefault();
        $this->approveAllRoles();
        app(SubscriptionRenewalReadinessService::class)->addSignoff([
            'signer_role' => SubscriptionRenewalSignoff::ROLE_TECHNICAL,
            'decision' => SubscriptionRenewalSignoff::DECISION_APPROVED_WITH_RISK,
        ]);

        $this->assertSame('WATCH', $this->service()->evaluate()['decision']);
    }

    public function test_no_go_on_blocking_risk(): void
    {
        app(SubscriptionRenewalPolicyService::class)->ensureDefault();
        $this->approveAllRoles();
        app(SubscriptionRenewalRiskGovernanceService::class)->create(['area' => 'RENEWAL_APPROVAL', 'severity' => 'CRITICAL', 'title' => 'C']);

        $this->assertSame('NO_GO', $this->service()->evaluate()['decision']);
    }
}
