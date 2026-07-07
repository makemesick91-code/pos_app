<?php

namespace Tests\Feature;

use App\Models\ProductionHandoverPackage;
use App\Services\Handover\PilotClosureService;
use App\Services\Handover\ProductionHandoverGoNoGoService;
use App\Services\Handover\ProductionHandoverService;
use App\Services\Handover\ProductionSignoffService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHandoverGoNoGoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductionHandoverGoNoGoService
    {
        return app(ProductionHandoverGoNoGoService::class);
    }

    private function readyPackageWithApprovals(): ProductionHandoverPackage
    {
        $package = app(ProductionHandoverService::class)->create([]);
        $package = app(ProductionHandoverService::class)->markReady($package);

        $signoffs = app(ProductionSignoffService::class);
        foreach (['OWNER', 'TECHNICAL', 'SUPPORT'] as $role) {
            $signoffs->addSignoff($package, ['signer_role' => $role, 'decision' => 'APPROVED']);
        }

        return $package;
    }

    public function test_gate_references_are_all_registered(): void
    {
        $gates = $this->service()->evaluate()['gates'];

        foreach ($gates as $name => $ok) {
            $this->assertTrue($ok, "Gate {$name} should be wired.");
        }
    }

    public function test_go_when_all_gates_and_signoffs_pass(): void
    {
        app(PilotClosureService::class)->create([]); // GO closure (no defects)
        $this->readyPackageWithApprovals();

        $this->assertSame(ProductionHandoverGoNoGoService::DECISION_GO, $this->service()->evaluate()['decision']);
    }

    public function test_watch_when_approved_with_risk(): void
    {
        app(PilotClosureService::class)->create([]);
        $package = app(ProductionHandoverService::class)->create([]);
        $package = app(ProductionHandoverService::class)->markReady($package);
        $signoffs = app(ProductionSignoffService::class);
        $signoffs->addSignoff($package, ['signer_role' => 'OWNER', 'decision' => 'APPROVED']);
        $signoffs->addSignoff($package, ['signer_role' => 'TECHNICAL', 'decision' => 'APPROVED_WITH_RISK']);
        $signoffs->addSignoff($package, ['signer_role' => 'SUPPORT', 'decision' => 'APPROVED']);

        $this->assertSame(ProductionHandoverGoNoGoService::DECISION_WATCH, $this->service()->evaluate()['decision']);
    }

    public function test_no_go_when_rejected_signoff(): void
    {
        app(PilotClosureService::class)->create([]);
        $package = app(ProductionHandoverService::class)->create([]);
        $package = app(ProductionHandoverService::class)->markReady($package);
        app(ProductionSignoffService::class)->addSignoff($package, ['signer_role' => 'OWNER', 'decision' => 'REJECTED']);

        $this->assertSame(ProductionHandoverGoNoGoService::DECISION_NO_GO, $this->service()->evaluate()['decision']);
    }

    public function test_no_go_when_open_blocking_defect(): void
    {
        app(PilotDefectService::class)->create(['title' => 'x', 'area' => 'SYNC', 'severity' => 'BLOCKER']);
        $this->readyPackageWithApprovals();

        $this->assertSame(ProductionHandoverGoNoGoService::DECISION_NO_GO, $this->service()->evaluate()['decision']);
    }
}
