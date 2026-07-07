<?php

namespace Tests\Feature;

use App\Services\Operations\PostHandoverGovernanceReportService;
use App\Services\Operations\ProductionIncidentService;
use App\Services\Operations\MaintenanceWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostHandoverGovernanceReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PostHandoverGovernanceReportService
    {
        return app(PostHandoverGovernanceReportService::class);
    }

    public function test_all_gates_wired(): void
    {
        $gates = $this->service()->evaluate()['gates'];

        foreach ($gates as $name => $ok) {
            $this->assertTrue($ok, "Gate {$name} should be wired.");
        }
    }

    public function test_clean_environment_is_go(): void
    {
        $this->assertSame(
            PostHandoverGovernanceReportService::DECISION_GO,
            $this->service()->evaluate()['decision'],
        );
    }

    public function test_open_blocking_incident_forces_no_go(): void
    {
        app(ProductionIncidentService::class)->create([
            'title' => 'x', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL',
        ]);

        $this->assertSame(
            PostHandoverGovernanceReportService::DECISION_NO_GO,
            $this->service()->evaluate()['decision'],
        );
    }

    public function test_high_risk_maintenance_without_rollback_forces_no_go(): void
    {
        app(MaintenanceWindowService::class)->create([
            'title' => 'risky', 'risk_level' => 'HIGH',
            'scheduled_start_at' => Carbon::now()->addDay(),
            'scheduled_end_at' => Carbon::now()->addDay()->addHour(),
        ]);

        $this->assertSame(
            PostHandoverGovernanceReportService::DECISION_NO_GO,
            $this->service()->evaluate()['decision'],
        );
    }

    public function test_open_p2_incident_forces_watch(): void
    {
        app(ProductionIncidentService::class)->create([
            'title' => 'x', 'area' => 'REPORTING', 'severity' => 'P2', 'impact' => 'DEGRADED',
        ]);

        $this->assertSame(
            PostHandoverGovernanceReportService::DECISION_WATCH,
            $this->service()->evaluate()['decision'],
        );
    }
}
