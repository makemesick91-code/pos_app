<?php

namespace Tests\Feature;

use App\Services\Operations\ProductionIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOperationsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_health_command_runs(): void
    {
        $this->artisan('production:ops-health')->assertExitCode(0);
        $this->artisan('production:ops-health --json')->assertExitCode(0);
    }

    public function test_incident_summary_command_runs(): void
    {
        $this->artisan('production:incident-summary --json')->assertExitCode(0);
    }

    public function test_backup_governance_check_command_runs(): void
    {
        $this->artisan('production:backup-governance-check --json')->assertExitCode(0);
    }

    public function test_post_handover_go_no_go_command_runs(): void
    {
        $this->artisan('production:post-handover-go-no-go --json')->assertExitCode(0);
    }

    public function test_incident_summary_strict_fails_on_open_blocking_incident(): void
    {
        app(ProductionIncidentService::class)->create([
            'title' => 'x', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL',
        ]);

        $this->artisan('production:incident-summary --strict')->assertExitCode(1);
    }

    public function test_prior_sprint_gate_commands_still_run(): void
    {
        $this->artisan('production:readiness-check --json')->assertExitCode(0);
        $this->artisan('pilot:rc-check --json')->assertExitCode(0);
        $this->artisan('pilot:deployment-check --json')->assertExitCode(0);
        $this->artisan('pilot:daily-monitoring-check --json')->assertExitCode(0);
        $this->artisan('pilot:stabilization-go-no-go --json')->assertExitCode(0);
        $this->artisan('production:handover-go-no-go --json')->assertExitCode(0);
    }
}
