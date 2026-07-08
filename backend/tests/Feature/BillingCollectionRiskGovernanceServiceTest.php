<?php

namespace Tests\Feature;

use App\Models\SaasBillingCollectionRisk;
use App\Models\User;
use App\Services\BillingCollection\BillingCollectionRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class BillingCollectionRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingCollectionRiskGovernanceService
    {
        return app(BillingCollectionRiskGovernanceService::class);
    }

    public function test_open_high_risk_forces_no_go(): void
    {
        $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'blocking']);

        $this->assertSame('NO_GO', $this->service()->summary()['decision']);
    }

    public function test_open_medium_risk_forces_watch(): void
    {
        $this->service()->create(['area' => 'DISPUTE', 'severity' => 'MEDIUM', 'title' => 'watch']);

        $this->assertSame('WATCH', $this->service()->summary()['decision']);
    }

    public function test_accepted_high_risk_requires_approver_reason_expiry(): void
    {
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($risk, ['reason' => 'documented']); // no expiry/approver
    }

    public function test_valid_accepted_high_risk_unblocks(): void
    {
        $approver = User::factory()->create();
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver_id' => $approver->id,
            'expires_at' => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame('GO', $this->service()->summary()['decision']);
    }

    public function test_expired_accepted_risk_reblocks(): void
    {
        $approver = User::factory()->create();
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver_id' => $approver->id,
            'expires_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $future = Carbon::now()->addDays(10);
        $this->assertSame('NO_GO', $this->service()->summary($future)['decision']);
    }

    public function test_closed_risks_do_not_block(): void
    {
        $risk = $this->service()->create(['area' => 'DISPUTE', 'severity' => 'CRITICAL', 'title' => 'x']);
        $this->service()->close($risk);

        $this->assertSame('GO', $this->service()->summary()['decision']);
        $this->assertSame(SaasBillingCollectionRisk::STATUS_CLOSED, $risk->fresh()->status);
    }
}
