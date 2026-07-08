<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalRisk;
use App\Models\User;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionRenewalRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalRiskGovernanceService
    {
        return app(SubscriptionRenewalRiskGovernanceService::class);
    }

    public function test_open_high_risk_forces_no_go(): void
    {
        $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'High']);
        $this->assertSame('NO_GO', $this->service()->summary()['decision']);
    }

    public function test_open_medium_risk_forces_watch(): void
    {
        $this->service()->create(['area' => 'GRACE_PERIOD', 'severity' => 'MEDIUM', 'title' => 'Medium']);
        $this->assertSame('WATCH', $this->service()->summary()['decision']);
    }

    public function test_accepted_high_risk_requires_approver_reason_expiry(): void
    {
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'High']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($risk, ['reason' => 'ok']); // missing expiry
    }

    public function test_valid_accepted_high_risk_unblocks(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'High']);

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver' => $user->id,
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertSame('GO', $this->service()->summary()['decision']);
    }

    public function test_expired_accepted_high_risk_reblocks(): void
    {
        $user = User::factory()->platformAdmin()->create();
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'HIGH', 'title' => 'High']);

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented',
            'approver' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame('NO_GO', $this->service()->summary()['decision']);
    }

    public function test_closed_risk_does_not_block(): void
    {
        $risk = $this->service()->create(['area' => 'PAYMENT_DELAY', 'severity' => 'CRITICAL', 'title' => 'C']);
        $this->service()->close($risk);

        $this->assertSame('GO', $this->service()->summary()['decision']);
    }
}
