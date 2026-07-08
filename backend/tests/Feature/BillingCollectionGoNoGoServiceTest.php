<?php

namespace Tests\Feature;

use App\Services\BillingCollection\BillingCollectionReadinessService;
use App\Services\BillingCollection\BillingCollectionGoNoGoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCollectionGoNoGoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingCollectionGoNoGoService
    {
        return app(BillingCollectionGoNoGoService::class);
    }

    private function approveAllRoles(): void
    {
        $readiness = app(BillingCollectionReadinessService::class);
        foreach ((array) config('billing_collection.required_signoff_roles') as $role) {
            $readiness->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_aggregates_prior_sprint_gates(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('gates', $report);
        foreach (['release_gate', 'commercial_launch_gate', 'public_website_gate', 'sales_pipeline_gate'] as $gate) {
            $this->assertArrayHasKey($gate, $report['gates']);
            $this->assertTrue($report['gates'][$gate], "{$gate} commands must be registered.");
        }
    }

    public function test_go_when_prior_gates_and_readiness_pass(): void
    {
        $this->approveAllRoles();

        $this->assertSame('GO', $this->service()->evaluate()['decision']);
    }

    public function test_watch_when_approved_with_risk_exists(): void
    {
        $readiness = app(BillingCollectionReadinessService::class);
        foreach ((array) config('billing_collection.required_signoff_roles') as $role) {
            $decision = $role === 'SALES' ? 'APPROVED_WITH_RISK' : 'APPROVED';
            $readiness->addSignoff(['signer_role' => $role, 'decision' => $decision]);
        }

        $this->assertSame('WATCH', $this->service()->evaluate()['decision']);
    }

    public function test_no_go_when_missing_docs(): void
    {
        $this->approveAllRoles();
        config()->set('billing_collection.required_docs', ['docs/billing-collection/does-not-exist.md']);

        $this->assertSame('NO_GO', $this->service()->evaluate()['decision']);
    }
}
