<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalSignoff;
use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalReadinessService
    {
        return app(SubscriptionRenewalReadinessService::class);
    }

    private function approveAllRoles(string $decision = SubscriptionRenewalSignoff::DECISION_APPROVED): void
    {
        foreach ((array) config('subscription_renewal.required_signoff_roles') as $role) {
            $this->service()->addSignoff(['signer_role' => $role, 'decision' => $decision]);
        }
    }

    public function test_docs_present_no_risk_valid_signoffs_gives_go(): void
    {
        app(\App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService::class)->ensureDefault();
        $this->approveAllRoles();

        $this->assertSame('GO', $this->service()->evaluate()['decision']);
    }

    public function test_rejected_signoff_gives_no_go(): void
    {
        $this->approveAllRoles();
        $this->service()->addSignoff([
            'signer_role' => SubscriptionRenewalSignoff::ROLE_FINANCE,
            'decision' => SubscriptionRenewalSignoff::DECISION_REJECTED,
        ]);

        $this->assertSame('NO_GO', $this->service()->evaluate()['decision']);
    }

    public function test_approved_with_risk_gives_watch(): void
    {
        app(\App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService::class)->ensureDefault();
        $this->approveAllRoles();
        $this->service()->addSignoff([
            'signer_role' => SubscriptionRenewalSignoff::ROLE_TECHNICAL,
            'decision' => SubscriptionRenewalSignoff::DECISION_APPROVED_WITH_RISK,
        ]);

        $this->assertSame('WATCH', $this->service()->evaluate()['decision']);
    }

    public function test_forbidden_automation_flag_gives_no_go(): void
    {
        config(['subscription_renewal.auto_charge_allowed' => true]);
        $this->approveAllRoles();

        $report = $this->service()->evaluate();
        $this->assertSame('NO_GO', $report['decision']);
        $this->assertContains('auto_charge_allowed', $report['config_guardrails']['enabled_forbidden_automation']);
    }

    public function test_open_high_risk_gives_no_go(): void
    {
        app(SubscriptionRenewalRiskGovernanceService::class)->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'H']);
        $this->approveAllRoles();

        $this->assertSame('NO_GO', $this->service()->evaluate()['decision']);
    }

    public function test_output_is_secret_safe(): void
    {
        $json = json_encode($this->service()->evaluate());
        $this->assertStringNotContainsString('sk_live', (string) $json);
    }
}
