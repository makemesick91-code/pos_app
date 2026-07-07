<?php

namespace Tests\Feature;

use App\Models\PublicWebsiteRisk;
use App\Models\User;
use App\Services\PublicWebsite\PublicWebsiteRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class PublicWebsiteRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublicWebsiteRiskGovernanceService
    {
        return app(PublicWebsiteRiskGovernanceService::class);
    }

    public function test_open_high_risk_forces_no_go(): void
    {
        $this->service()->create(['area' => 'CONTENT_CLAIM', 'severity' => 'HIGH', 'title' => 'Unsupported claim']);

        $this->assertSame(PublicWebsiteRiskGovernanceService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_open_medium_without_mitigation_is_watch(): void
    {
        $this->service()->create(['area' => 'SEO', 'severity' => 'MEDIUM', 'title' => 'Meta gaps']);

        $this->assertSame(PublicWebsiteRiskGovernanceService::DECISION_WATCH, $this->service()->summary()['decision']);
    }

    public function test_accepted_high_risk_clears_no_go(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $risk = $this->service()->create(['area' => 'PRIVACY', 'severity' => 'HIGH', 'title' => 'Privacy gap']);

        $this->service()->acceptRisk($risk, [
            'reason' => 'documented', 'approver' => $admin->id,
            'expires_at' => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame(PublicWebsiteRiskGovernanceService::DECISION_GO, $this->service()->summary()['decision']);
    }

    public function test_accept_high_requires_expiry(): void
    {
        $risk = $this->service()->create(['area' => 'SECURITY', 'severity' => 'HIGH', 'title' => 'x']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($risk, ['reason' => 'no expiry', 'approver' => 1]);
    }

    public function test_expired_accepted_high_risk_forces_no_go(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $risk = $this->service()->create(['area' => 'LEGAL_TERMS', 'severity' => 'HIGH', 'title' => 'x']);
        $risk = $this->service()->acceptRisk($risk, [
            'reason' => 'temp', 'approver' => $admin->id,
            'expires_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $summary = $this->service()->summary(Carbon::now()->addDays(10));
        $this->assertSame(PublicWebsiteRisk::STATUS_ACCEPTED_RISK, $risk->status);
        $this->assertSame(PublicWebsiteRiskGovernanceService::DECISION_NO_GO, $summary['decision']);
    }
}
