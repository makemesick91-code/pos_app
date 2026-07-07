<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pilot\AcceptedRiskGovernanceService;
use App\Services\Pilot\PilotDefectService;
use App\Services\Pilot\PilotStabilizationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotStabilizationReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $defects;

    private PilotStabilizationReportService $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defects = app(PilotDefectService::class);
        $this->report = app(PilotStabilizationReportService::class);
    }

    public function test_go_when_no_blocker_and_gates_healthy(): void
    {
        $report = $this->report->evaluate();

        $this->assertSame(PilotStabilizationReportService::DECISION_GO, $report['decision']);
        $this->assertTrue($report['gates']['stabilization_gate']);
        $this->assertTrue($report['gates']['release_gate']);
    }

    public function test_watch_when_major_open(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'MAJOR']);

        $this->assertSame(PilotStabilizationReportService::DECISION_WATCH, $this->report->evaluate()['decision']);
    }

    public function test_no_go_when_open_blocker(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);

        $this->assertSame(PilotStabilizationReportService::DECISION_NO_GO, $this->report->evaluate()['decision']);
    }

    public function test_watch_when_blocker_accepted_as_valid_risk(): void
    {
        $defect = $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);
        $approver = User::factory()->platformAdmin()->create();
        app(AcceptedRiskGovernanceService::class)->accept($defect, [
            'reason' => 'out of scope',
            'approver' => $approver->id,
            'expires_at' => now()->addDays(5),
        ]);

        $this->assertSame(PilotStabilizationReportService::DECISION_WATCH, $this->report->evaluate()['decision']);
    }
}
