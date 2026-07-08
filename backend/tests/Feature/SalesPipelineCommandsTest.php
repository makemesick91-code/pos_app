<?php

namespace Tests\Feature;

use App\Services\SalesPipeline\SalesPipelineReadinessService;
use App\Services\SalesPipeline\SalesPipelineStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function seedReady(): void
    {
        app(SalesPipelineStageService::class)->ensureDefaults();
        foreach ((array) config('sales_pipeline.required_signoff_roles') as $role) {
            app(SalesPipelineReadinessService::class)->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_readiness_command_is_no_go_when_empty(): void
    {
        $this->artisan('sales-pipeline:readiness')->assertExitCode(1);
    }

    public function test_readiness_command_json_runs(): void
    {
        $this->artisan('sales-pipeline:readiness --json')->assertExitCode(1);
    }

    public function test_lead_summary_command_is_go(): void
    {
        $this->artisan('sales-pipeline:lead-summary --json --strict')->assertExitCode(0);
    }

    public function test_activity_summary_command_is_go(): void
    {
        $this->artisan('sales-pipeline:activity-summary')
            ->expectsOutputToContain('manual_follow_up_only: PASS')
            ->assertExitCode(0);
    }

    public function test_go_no_go_command_runs_json(): void
    {
        $this->artisan('sales-pipeline:go-no-go --json')->assertExitCode(1);
    }

    public function test_readiness_and_go_no_go_are_go_when_seeded(): void
    {
        $this->seedReady();

        $this->artisan('sales-pipeline:readiness --strict')->assertExitCode(0);
        $this->artisan('sales-pipeline:go-no-go --strict')->assertExitCode(0);
    }

    public function test_go_no_go_json_output_is_secret_free(): void
    {
        $this->seedReady();
        $this->artisan('sales-pipeline:go-no-go --json')->assertExitCode(0);
        // Output must not contain any redaction leak markers or secret keys.
        $this->artisan('sales-pipeline:go-no-go --json')->doesntExpectOutputToContain('sk_live_');
    }
}
