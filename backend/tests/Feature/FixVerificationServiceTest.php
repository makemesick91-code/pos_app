<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\User;
use App\Services\Pilot\FixVerificationService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $defects;

    private FixVerificationService $verification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defects = app(PilotDefectService::class);
        $this->verification = app(FixVerificationService::class);
    }

    private function defect(): PilotDefect
    {
        return $this->defects->create(['title' => 'x', 'area' => 'OTHER', 'severity' => 'MAJOR']);
    }

    public function test_mark_fixed_sets_fixed_at_and_event(): void
    {
        $defect = $this->verification->markFixed($this->defect());

        $this->assertSame(PilotDefect::STATUS_FIXED, $defect->status);
        $this->assertNotNull($defect->fixed_at);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_FIXED)->count());
    }

    public function test_request_retest_moves_to_retest_and_event(): void
    {
        $defect = $this->verification->requestRetest($this->defect());

        $this->assertSame(PilotDefect::STATUS_RETEST, $defect->status);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_RETEST_REQUESTED)->count());
    }

    public function test_verify_pass_sets_result_and_event(): void
    {
        $verifier = User::factory()->platformAdmin()->create();
        $defect = $this->verification->verify($this->defect(), true, $verifier);

        $this->assertSame(PilotDefect::STATUS_VERIFIED, $defect->status);
        $this->assertSame(PilotDefect::VERIFICATION_PASS, $defect->verification_result);
        $this->assertSame($verifier->id, $defect->verified_by);
        $this->assertNotNull($defect->verified_at);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_VERIFIED)->count());
    }

    public function test_verify_fail_returns_to_in_progress_and_event(): void
    {
        $defect = $this->verification->verify($this->defect(), false);

        $this->assertSame(PilotDefect::STATUS_IN_PROGRESS, $defect->status);
        $this->assertSame(PilotDefect::VERIFICATION_FAIL, $defect->verification_result);
    }

    public function test_verify_pass_with_close_closes(): void
    {
        $defect = $this->verification->verify($this->defect(), true, null, null, true);

        $this->assertSame(PilotDefect::STATUS_CLOSED, $defect->status);
        $this->assertNotNull($defect->closed_at);
    }
}
