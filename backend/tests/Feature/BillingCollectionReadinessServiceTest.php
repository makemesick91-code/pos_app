<?php

namespace Tests\Feature;

use App\Models\SaasBillingCollectionSignoff;
use App\Services\BillingCollection\BillingCollectionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCollectionReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingCollectionReadinessService
    {
        return app(BillingCollectionReadinessService::class);
    }

    private function approveAllRoles(): void
    {
        foreach ((array) config('billing_collection.required_signoff_roles') as $role) {
            $this->service()->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_missing_docs_cause_no_go(): void
    {
        config()->set('billing_collection.required_docs', ['docs/billing-collection/does-not-exist.md']);

        $report = $this->service()->evaluate();
        $this->assertSame('NO_GO', $report['decision']);
        $this->assertSame('NO_GO', $report['billing_collection_docs']['decision']);
    }

    public function test_docs_present_no_blocking_risks_and_signoffs_valid_gives_go(): void
    {
        $this->approveAllRoles();

        $report = $this->service()->evaluate();
        $this->assertSame('GO', $report['decision']);
    }

    public function test_rejected_signoff_gives_no_go(): void
    {
        $this->approveAllRoles();
        $this->service()->addSignoff(['signer_role' => 'FINANCE', 'decision' => 'REJECTED']);

        $this->assertSame('NO_GO', $this->service()->evaluate()['decision']);
    }

    public function test_approved_with_risk_gives_watch(): void
    {
        foreach ((array) config('billing_collection.required_signoff_roles') as $role) {
            $decision = $role === 'OWNER' ? 'APPROVED_WITH_RISK' : 'APPROVED';
            $this->service()->addSignoff(['signer_role' => $role, 'decision' => $decision]);
        }

        $this->assertSame('WATCH', $this->service()->evaluate()['decision']);
    }

    public function test_forbidden_automation_config_true_gives_no_go(): void
    {
        $this->approveAllRoles();
        config()->set('billing_collection.auto_charge_allowed', true);

        $report = $this->service()->evaluate();
        $this->assertSame('NO_GO', $report['decision']);
        $this->assertSame('NO_GO', $report['config_guardrails']['decision']);
        $this->assertContains('auto_charge_allowed', $report['config_guardrails']['enabled_forbidden_automation']);
    }

    public function test_output_is_secret_safe(): void
    {
        $signoff = $this->service()->addSignoff([
            'signer_role' => 'TECHNICAL', 'decision' => 'APPROVED',
            'notes' => 'token: ghp_secretvalue',
        ]);

        $this->assertStringNotContainsString('ghp_secretvalue', (string) $signoff->notes);
        $this->assertInstanceOf(SaasBillingCollectionSignoff::class, $signoff);
    }
}
