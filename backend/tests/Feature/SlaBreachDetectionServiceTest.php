<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Services\Pilot\PilotDefectService;
use App\Services\Pilot\SlaBreachDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaBreachDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $defects;

    private SlaBreachDetectionService $sla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defects = app(PilotDefectService::class);
        $this->sla = app(SlaBreachDetectionService::class);
    }

    public function test_computes_sla_due_by_severity(): void
    {
        $defect = $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);

        // BLOCKER SLA = 8h.
        $this->assertSame(
            $defect->created_at->copy()->addHours(8)->timestamp,
            $this->sla->dueAtFor($defect)->timestamp,
        );
    }

    public function test_detects_overdue_defects_read_only(): void
    {
        $defect = $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);
        $defect->forceFill(['sla_due_at' => now()->subHour()])->save();

        $detection = $this->sla->detect();

        $this->assertSame(1, $detection['count']);
        // Read-only: nothing persisted, no event appended.
        $this->assertNull($defect->fresh()->sla_breached_at);
        $this->assertSame(0, $defect->events()->where('event_type', PilotDefectEvent::TYPE_SLA_BREACHED)->count());
    }

    public function test_mark_mode_sets_breached_and_appends_event(): void
    {
        $defect = $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);
        $defect->forceFill(['sla_due_at' => now()->subHour()])->save();

        $marked = $this->sla->markBreaches();

        $this->assertSame(1, $marked);
        $this->assertNotNull($defect->fresh()->sla_breached_at);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_SLA_BREACHED)->count());
    }

    public function test_not_yet_due_is_not_breached(): void
    {
        $this->defects->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'MINOR']);

        $this->assertSame(0, $this->sla->detect()['count']);
    }
}
