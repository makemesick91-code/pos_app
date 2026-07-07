<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\User;
use App\Services\Handover\AcceptedRiskFinalReviewService;
use App\Services\Pilot\AcceptedRiskGovernanceService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptedRiskFinalReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedAccepted(string $severity, ?string $expiresAt): PilotDefect
    {
        $defect = app(PilotDefectService::class)->create([
            'title' => 'risk', 'area' => 'SYNC', 'severity' => $severity,
        ]);

        return app(AcceptedRiskGovernanceService::class)->accept($defect, [
            'reason' => 'documented workaround',
            'approver' => User::factory()->platformAdmin()->create()->id,
            'expires_at' => $expiresAt,
        ]);
    }

    private function review(): array
    {
        return app(AcceptedRiskFinalReviewService::class)->review();
    }

    public function test_no_accepted_risk_is_go(): void
    {
        $this->assertSame(AcceptedRiskFinalReviewService::DECISION_GO, $this->review()['decision']);
    }

    public function test_valid_accepted_risk_is_watch_and_preserves_severity(): void
    {
        $this->seedAccepted('CRITICAL', now()->addDays(7)->toDateString());

        $review = $this->review();

        $this->assertSame(AcceptedRiskFinalReviewService::DECISION_WATCH, $review['decision']);
        $this->assertSame(1, $review['counts']['valid']);
        $this->assertSame('CRITICAL', $review['items'][0]['original_severity']);
    }

    public function test_expired_blocking_accepted_risk_is_no_go(): void
    {
        $this->seedAccepted('BLOCKER', now()->subDay()->toDateString());

        $review = $this->review();

        $this->assertSame(AcceptedRiskFinalReviewService::DECISION_NO_GO, $review['decision']);
        $this->assertSame(1, $review['counts']['expired_blocking']);
    }

    public function test_incomplete_acceptance_missing_approver_is_watch(): void
    {
        // Force a missing-approver acceptance directly (governance normally requires one).
        $defect = app(PilotDefectService::class)->create(['title' => 'x', 'area' => 'SYNC', 'severity' => 'MAJOR']);
        $defect->update([
            'status' => PilotDefect::STATUS_ACCEPTED_RISK,
            'accepted_risk_at' => now(),
            'accepted_risk_by' => null,
            'accepted_risk_reason' => 'reason',
            'accepted_risk_expires_at' => now()->addDays(3),
        ]);

        $review = $this->review();

        $this->assertSame(AcceptedRiskFinalReviewService::DECISION_WATCH, $review['decision']);
        $this->assertSame(1, $review['counts']['incomplete']);
        $this->assertContains('approver', $review['items'][0]['missing']);
    }
}
