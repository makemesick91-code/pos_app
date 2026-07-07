<?php

namespace Tests\Feature;

use App\Services\Operations\ProductionIncidentService;
use App\Services\Operations\SupportSlaGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SupportSlaGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_incidents_is_go(): void
    {
        $report = app(SupportSlaGovernanceService::class)->evaluate();

        $this->assertSame(SupportSlaGovernanceService::DECISION_GO, $report['decision']);
        $this->assertArrayHasKey('sla_targets', $report);
    }

    public function test_sla_breached_blocking_incident_forces_no_go(): void
    {
        app(ProductionIncidentService::class)->create([
            'title' => 'x', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL',
        ], null, Carbon::now()->subDays(2));

        $report = app(SupportSlaGovernanceService::class)->evaluate();

        $this->assertSame(SupportSlaGovernanceService::DECISION_NO_GO, $report['decision']);
    }
}
