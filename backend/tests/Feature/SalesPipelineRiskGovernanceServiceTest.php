<?php

namespace Tests\Feature;

use App\Models\SalesPipelineRisk;
use App\Models\User;
use App\Services\SalesPipeline\SalesPipelineRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class SalesPipelineRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SalesPipelineRiskGovernanceService
    {
        return app(SalesPipelineRiskGovernanceService::class);
    }

    public function test_open_high_risk_forces_no_go(): void
    {
        $this->service()->create(['area' => 'LEAD_QUALITY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->assertSame(SalesPipelineRiskGovernanceService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_open_medium_risk_forces_watch(): void
    {
        $this->service()->create(['area' => 'DATA_QUALITY', 'severity' => 'MEDIUM', 'title' => 'x']);

        $this->assertSame(SalesPipelineRiskGovernanceService::DECISION_WATCH, $this->service()->summary()['decision']);
    }

    public function test_accepted_high_risk_requires_approver_reason_and_expiry(): void
    {
        $risk = $this->service()->create(['area' => 'LEGAL_PRIVACY', 'severity' => 'HIGH', 'title' => 'x']);
        $approver = User::factory()->create();

        // Missing reason.
        try {
            $this->service()->acceptRisk($risk, []);
            $this->fail('Expected exception for missing reason.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        // Missing expiry.
        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($risk, ['reason' => 'documented', 'approver' => $approver->id]);
    }

    public function test_valid_accepted_high_risk_unblocks(): void
    {
        $risk = $this->service()->create(['area' => 'OPERATIONS', 'severity' => 'HIGH', 'title' => 'x']);
        $approver = User::factory()->create();

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver' => $approver->id,
            'expires_at' => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame(SalesPipelineRiskGovernanceService::DECISION_GO, $this->service()->summary()['decision']);
    }

    public function test_expired_accepted_risk_blocks(): void
    {
        $risk = $this->service()->create(['area' => 'OPERATIONS', 'severity' => 'HIGH', 'title' => 'x']);
        $approver = User::factory()->create();

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver' => $approver->id,
            'expires_at' => Carbon::now()->addDays(1)->toDateString(),
        ]);

        // Evaluate as-of a future date past expiry.
        $this->assertSame(
            SalesPipelineRiskGovernanceService::DECISION_NO_GO,
            $this->service()->summary(Carbon::now()->addDays(10))['decision'],
        );
    }

    public function test_closed_risks_do_not_block(): void
    {
        $risk = $this->service()->create(['area' => 'LEAD_QUALITY', 'severity' => 'CRITICAL', 'title' => 'x']);
        $this->service()->close($risk);

        $this->assertSame(SalesPipelineRiskGovernanceService::DECISION_GO, $this->service()->summary()['decision']);
    }
}
