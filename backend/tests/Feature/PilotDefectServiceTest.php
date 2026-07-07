<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PilotDefectServiceTest extends TestCase
{
    use RefreshDatabase;

    private PilotDefectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PilotDefectService::class);
    }

    public function test_can_create_defect_and_appends_created_event(): void
    {
        $defect = $this->service->create([
            'title' => 'Cash sale fails',
            'area' => 'CASHIER',
            'severity' => PilotDefect::SEVERITY_MAJOR,
        ]);

        $this->assertDatabaseHas('pilot_defects', ['id' => $defect->id, 'severity' => 'MAJOR']);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_CREATED)->count());
        $this->assertNotNull($defect->sla_due_at);
    }

    public function test_default_blocking_true_for_blocker_and_critical(): void
    {
        $blocker = $this->service->create(['title' => 'a', 'area' => 'SYNC', 'severity' => 'BLOCKER']);
        $critical = $this->service->create(['title' => 'b', 'area' => 'SYNC', 'severity' => 'CRITICAL']);
        $minor = $this->service->create(['title' => 'c', 'area' => 'SYNC', 'severity' => 'MINOR']);

        $this->assertTrue($blocker->blocking);
        $this->assertTrue($critical->blocking);
        $this->assertFalse($minor->blocking);
    }

    public function test_store_must_belong_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->create([
            'title' => 'x',
            'area' => 'OTHER',
            'severity' => 'MINOR',
            'tenant_id' => $tenantA->id,
            'store_id' => $storeB->id,
        ]);
    }

    public function test_secret_like_values_are_sanitized(): void
    {
        $defect = $this->service->create([
            'title' => 'Login broke',
            'area' => 'AUTH',
            'severity' => 'MINOR',
            'description' => 'password=hunter2 leaked in log',
            'metadata' => ['api_key' => 'sk_live_123', 'note' => 'ok'],
        ]);

        $this->assertStringNotContainsString('hunter2', (string) $defect->description);
        $this->assertSame('[REDACTED]', $defect->metadata['api_key']);
        $this->assertSame('ok', $defect->metadata['note']);
    }

    public function test_status_and_severity_changes_append_events(): void
    {
        $defect = $this->service->create(['title' => 'x', 'area' => 'OTHER', 'severity' => 'MINOR']);

        $this->service->transitionStatus($defect, PilotDefect::STATUS_IN_PROGRESS);
        $this->service->update($defect, ['severity' => 'MAJOR']);

        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_STATUS_CHANGED)->count());
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_SEVERITY_CHANGED)->count());
    }

    public function test_history_is_not_deleted_on_close(): void
    {
        $defect = $this->service->create(['title' => 'x', 'area' => 'OTHER', 'severity' => 'MINOR']);
        $before = $defect->events()->count();

        $this->service->transitionStatus($defect, PilotDefect::STATUS_CLOSED);

        $this->assertGreaterThan($before, $defect->events()->count());
        $this->assertNotNull($defect->fresh()->closed_at);
    }

    public function test_assign_requires_no_history_loss(): void
    {
        $defect = $this->service->create(['title' => 'x', 'area' => 'OTHER', 'severity' => 'MINOR']);
        $user = User::factory()->create();

        $this->service->assign($defect, $user->id);

        $this->assertSame($user->id, $defect->fresh()->assigned_to);
        $this->assertSame(1, $defect->events()->where('event_type', PilotDefectEvent::TYPE_ASSIGNED)->count());
    }
}
