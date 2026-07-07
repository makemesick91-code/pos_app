<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\User;
use App\Services\Pilot\AcceptedRiskGovernanceService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AcceptedRiskGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $defects;

    private AcceptedRiskGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defects = app(PilotDefectService::class);
        $this->service = app(AcceptedRiskGovernanceService::class);
    }

    private function defect(string $severity = 'CRITICAL'): PilotDefect
    {
        return $this->defects->create(['title' => 'x', 'area' => 'OTHER', 'severity' => $severity]);
    }

    public function test_requires_approver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->accept($this->defect(), [
            'reason' => 'ok',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_requires_reason(): void
    {
        $approver = User::factory()->platformAdmin()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->service->accept($this->defect(), [
            'reason' => '',
            'approver' => $approver->id,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_requires_expiry_for_blocking_severity(): void
    {
        $approver = User::factory()->platformAdmin()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->service->accept($this->defect('BLOCKER'), [
            'reason' => 'known risk',
            'approver' => $approver->id,
        ]);
    }

    public function test_accepts_and_preserves_original_severity(): void
    {
        $approver = User::factory()->platformAdmin()->create();
        $defect = $this->defect('CRITICAL');

        $updated = $this->service->accept($defect, [
            'reason' => 'documented workaround',
            'approver' => $approver->id,
            'expires_at' => now()->addDays(3),
        ]);

        $this->assertSame(PilotDefect::STATUS_ACCEPTED_RISK, $updated->status);
        $this->assertSame('CRITICAL', $updated->severity);
        $this->assertTrue($updated->blocking);
        $this->assertSame($approver->id, $updated->accepted_risk_by);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_ACCEPTED_RISK)->count());
    }

    public function test_minor_can_be_accepted_without_expiry(): void
    {
        $approver = User::factory()->platformAdmin()->create();
        $updated = $this->service->accept($this->defect('MINOR'), [
            'reason' => 'cosmetic',
            'approver' => $approver->id,
        ]);

        $this->assertSame(PilotDefect::STATUS_ACCEPTED_RISK, $updated->status);
    }
}
