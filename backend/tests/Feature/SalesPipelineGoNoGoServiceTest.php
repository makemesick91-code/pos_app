<?php

namespace Tests\Feature;

use App\Services\SalesPipeline\SalesPipelineGoNoGoService;
use App\Services\SalesPipeline\SalesPipelineReadinessService;
use App\Services\SalesPipeline\SalesPipelineRiskGovernanceService;
use App\Services\SalesPipeline\SalesPipelineStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineGoNoGoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function goNoGo(): SalesPipelineGoNoGoService
    {
        return app(SalesPipelineGoNoGoService::class);
    }

    private function seedReady(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        foreach ((array) config('sales_pipeline.required_signoff_roles') as $role) {
            app(SalesPipelineReadinessService::class)->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_aggregates_prior_sprint_gates(): void
    {
        $report = $this->goNoGo()->evaluate();

        $this->assertArrayHasKey('gates', $report);
        foreach ((array) config('sales_pipeline.prior_sprint_gates') as $name => $commands) {
            $this->assertTrue($report['gates'][$name], "Gate {$name} should be registered.");
        }
    }

    public function test_go_when_previous_gates_and_readiness_pass(): void
    {
        $this->seedReady();

        $this->assertSame(SalesPipelineGoNoGoService::DECISION_GO, $this->goNoGo()->evaluate()['decision']);
    }

    public function test_watch_when_medium_risk_exists(): void
    {
        $this->seedReady();
        app(SalesPipelineRiskGovernanceService::class)->create(['area' => 'DATA_QUALITY', 'severity' => 'MEDIUM', 'title' => 'x']);

        $this->assertSame(SalesPipelineGoNoGoService::DECISION_WATCH, $this->goNoGo()->evaluate()['decision']);
    }

    public function test_no_go_when_blocking_risk_exists(): void
    {
        $this->seedReady();
        app(SalesPipelineRiskGovernanceService::class)->create(['area' => 'LEAD_QUALITY', 'severity' => 'CRITICAL', 'title' => 'x']);

        $this->assertSame(SalesPipelineGoNoGoService::DECISION_NO_GO, $this->goNoGo()->evaluate()['decision']);
    }

    public function test_no_go_when_canonical_stage_missing(): void
    {
        // No stages seeded.
        $this->assertSame(SalesPipelineGoNoGoService::DECISION_NO_GO, $this->goNoGo()->evaluate()['decision']);
    }
}
