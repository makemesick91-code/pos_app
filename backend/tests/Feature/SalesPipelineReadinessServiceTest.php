<?php

namespace Tests\Feature;

use App\Services\SalesPipeline\SalesPipelineReadinessService;
use App\Services\SalesPipeline\SalesPipelineRiskGovernanceService;
use App\Services\SalesPipeline\SalesPipelineStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function readiness(): SalesPipelineReadinessService
    {
        return app(SalesPipelineReadinessService::class);
    }

    private function approveAllRoles(): void
    {
        foreach ((array) config('sales_pipeline.required_signoff_roles') as $role) {
            $this->readiness()->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_missing_canonical_stages_forces_no_go(): void
    {
        $report = $this->readiness()->evaluate();
        $this->assertSame(SalesPipelineReadinessService::DECISION_NO_GO, $report['decision']);
    }

    public function test_go_when_stages_docs_signoffs_present_and_no_risks(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        $this->approveAllRoles();

        $report = $this->readiness()->evaluate();

        $this->assertSame(SalesPipelineReadinessService::DECISION_GO, $report['decision']);
        $this->assertSame('GO', $report['canonical_stages']['decision']);
        $this->assertSame('GO', $report['sales_pipeline_docs']['decision']);
    }

    public function test_rejected_signoff_gives_no_go(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        $this->approveAllRoles();
        $this->readiness()->addSignoff(['signer_role' => 'OWNER', 'decision' => 'REJECTED']);

        $this->assertSame(SalesPipelineReadinessService::DECISION_NO_GO, $this->readiness()->evaluate()['decision']);
    }

    public function test_approved_with_risk_gives_watch(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        foreach ((array) config('sales_pipeline.required_signoff_roles') as $role) {
            $decision = $role === 'OWNER' ? 'APPROVED_WITH_RISK' : 'APPROVED';
            $this->readiness()->addSignoff(['signer_role' => $role, 'decision' => $decision]);
        }

        $this->assertSame(SalesPipelineReadinessService::DECISION_WATCH, $this->readiness()->evaluate()['decision']);
    }

    public function test_open_high_risk_gives_no_go(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        $this->approveAllRoles();
        app(SalesPipelineRiskGovernanceService::class)->create(['area' => 'LEAD_QUALITY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->assertSame(SalesPipelineReadinessService::DECISION_NO_GO, $this->readiness()->evaluate()['decision']);
    }

    public function test_signoff_notes_are_secret_safe(): void
    {
        $signoff = $this->readiness()->addSignoff([
            'signer_role' => 'TECHNICAL',
            'decision' => 'APPROVED',
            'notes' => 'token: ghp_secretvalue',
        ]);

        $this->assertStringNotContainsString('ghp_secretvalue', (string) $signoff->notes);
    }
}
