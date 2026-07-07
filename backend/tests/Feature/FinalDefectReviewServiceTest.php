<?php

namespace Tests\Feature;

use App\Services\Handover\FinalDefectReviewService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalDefectReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private function defects(): PilotDefectService
    {
        return app(PilotDefectService::class);
    }

    private function review(): array
    {
        return app(FinalDefectReviewService::class)->review();
    }

    public function test_counts_open_defects_by_severity_and_status(): void
    {
        $this->defects()->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'MINOR']);
        $this->defects()->create(['title' => 'b', 'area' => 'CASHIER', 'severity' => 'MAJOR']);

        $review = $this->review();

        $this->assertSame(2, $review['counts']['total']);
        $this->assertArrayHasKey('by_severity', $review);
        $this->assertArrayHasKey('by_status', $review);
    }

    public function test_detects_unresolved_blocking_defect_and_is_no_go(): void
    {
        $this->defects()->create(['title' => 'boom', 'area' => 'PAYMENT_QRIS', 'severity' => 'CRITICAL']);

        $review = $this->review();

        $this->assertSame(FinalDefectReviewService::DECISION_NO_GO, $review['decision']);
        $this->assertNotEmpty($review['unresolved_blocking']);
        $this->assertSame('CRITICAL', $review['unresolved_blocking'][0]['severity']);
    }

    public function test_open_major_is_watch_and_includes_retest_state(): void
    {
        $this->defects()->create(['title' => 'meh', 'area' => 'REPORTING', 'severity' => 'MAJOR']);

        $review = $this->review();

        $this->assertSame(FinalDefectReviewService::DECISION_WATCH, $review['decision']);
        $this->assertArrayHasKey('fixed', $review['counts']);
        $this->assertArrayHasKey('verified', $review['counts']);
    }

    public function test_no_defects_is_go(): void
    {
        $this->assertSame(FinalDefectReviewService::DECISION_GO, $this->review()['decision']);
    }
}
