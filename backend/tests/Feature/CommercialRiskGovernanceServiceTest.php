<?php

namespace Tests\Feature;

use App\Models\CommercialLaunchRisk;
use App\Models\User;
use App\Services\Commercial\CommercialRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class CommercialRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CommercialRiskGovernanceService
    {
        return app(CommercialRiskGovernanceService::class);
    }

    public function test_no_risks_is_go(): void
    {
        $this->assertSame(CommercialRiskGovernanceService::DECISION_GO, $this->service()->summary()['decision']);
    }

    public function test_open_high_risk_is_no_go(): void
    {
        $this->service()->create([
            'area' => CommercialLaunchRisk::AREA_PRICING,
            'severity' => CommercialLaunchRisk::SEVERITY_HIGH,
            'title' => 'Pricing undefined',
        ]);

        $this->assertSame(CommercialRiskGovernanceService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_open_medium_risk_is_watch(): void
    {
        $this->service()->create([
            'area' => CommercialLaunchRisk::AREA_SUPPORT_CAPACITY,
            'severity' => CommercialLaunchRisk::SEVERITY_MEDIUM,
            'title' => 'Support tight',
            'mitigation' => 'Hire contractor',
        ]);

        $this->assertSame(CommercialRiskGovernanceService::DECISION_WATCH, $this->service()->summary()['decision']);
    }

    public function test_accepting_high_risk_requires_approver_and_expiry(): void
    {
        $risk = $this->service()->create([
            'area' => CommercialLaunchRisk::AREA_LEGAL_TERMS,
            'severity' => CommercialLaunchRisk::SEVERITY_HIGH,
            'title' => 'Terms pending',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($risk, ['reason' => 'legal reviewing']);
    }

    public function test_valid_accepted_high_risk_clears_no_go(): void
    {
        $service = $this->service();
        $risk = $service->create([
            'area' => CommercialLaunchRisk::AREA_LEGAL_TERMS,
            'severity' => CommercialLaunchRisk::SEVERITY_HIGH,
            'title' => 'Terms pending',
        ]);
        $service->acceptRisk($risk, [
            'reason' => 'legal reviewing',
            'approver' => User::factory()->create()->id,
            'expires_at' => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame(CommercialLaunchRisk::STATUS_ACCEPTED_RISK, $risk->fresh()->status);
        $this->assertSame(CommercialRiskGovernanceService::DECISION_GO, $service->summary()['decision']);
    }

    public function test_expired_accepted_high_risk_is_no_go(): void
    {
        $service = $this->service();
        $risk = $service->create([
            'area' => CommercialLaunchRisk::AREA_LEGAL_TERMS,
            'severity' => CommercialLaunchRisk::SEVERITY_HIGH,
            'title' => 'Terms pending',
        ]);
        $service->acceptRisk($risk, [
            'reason' => 'legal reviewing',
            'approver' => User::factory()->create()->id,
            'expires_at' => Carbon::now()->subDay()->toDateString(),
        ]);

        $this->assertSame(CommercialRiskGovernanceService::DECISION_NO_GO, $service->summary()['decision']);
    }
}
