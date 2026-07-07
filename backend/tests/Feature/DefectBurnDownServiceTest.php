<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Services\Pilot\AcceptedRiskGovernanceService;
use App\Services\Pilot\DefectBurnDownService;
use App\Services\Pilot\PilotDefectService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefectBurnDownServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $defects;

    private DefectBurnDownService $burnDown;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defects = app(PilotDefectService::class);
        $this->burnDown = app(DefectBurnDownService::class);
    }

    public function test_counts_by_severity_status_area(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'CASHIER', 'severity' => 'MINOR']);
        $this->defects->create(['title' => 'b', 'area' => 'SYNC', 'severity' => 'MINOR']);

        $summary = $this->burnDown->summary();

        $this->assertSame(2, $summary['by_severity']['MINOR']);
        $this->assertSame(1, $summary['by_area']['CASHIER']);
        $this->assertSame(2, $summary['counts']['total']);
    }

    public function test_open_blocker_forces_no_go(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);

        $this->assertSame(DefectBurnDownService::DECISION_NO_GO, $this->burnDown->summary()['decision']);
    }

    public function test_open_major_forces_watch(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'MAJOR']);

        $this->assertSame(DefectBurnDownService::DECISION_WATCH, $this->burnDown->summary()['decision']);
    }

    public function test_only_minor_trivial_open_is_go(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'MINOR']);
        $this->defects->create(['title' => 'b', 'area' => 'SYNC', 'severity' => 'TRIVIAL']);

        $this->assertSame(DefectBurnDownService::DECISION_GO, $this->burnDown->summary()['decision']);
    }

    public function test_accepted_risk_downgrades_no_go_to_watch_but_preserves_severity(): void
    {
        $defect = $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'CRITICAL']);
        $approver = User::factory()->platformAdmin()->create();

        app(AcceptedRiskGovernanceService::class)->accept($defect, [
            'reason' => 'Out of pilot scope; documented workaround.',
            'approver' => $approver->id,
            'expires_at' => now()->addDays(7),
        ]);

        $summary = $this->burnDown->summary();

        $this->assertSame(DefectBurnDownService::DECISION_WATCH, $summary['decision']);
        // Original severity preserved in the counts.
        $this->assertSame('CRITICAL', $defect->fresh()->severity);
        $this->assertSame(1, $summary['by_severity']['CRITICAL']);
    }
}
