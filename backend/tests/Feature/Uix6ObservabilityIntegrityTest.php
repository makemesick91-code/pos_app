<?php

namespace Tests\Feature;

use App\Models\ObservabilityHealthSnapshot;
use App\Services\SupportConsole\ObservabilityConsoleReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-6 — observability presentation is TRUTHFUL about freshness. The canonical
 * health aggregate folds "no scheduler runs" and a missing snapshot into an
 * aggregate "healthy"; the console must present those as UNKNOWN / stale, never
 * as a fabricated healthy state (UIX6-R011/R012/R013).
 */
class Uix6ObservabilityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function read(): ObservabilityConsoleReadService
    {
        return app(ObservabilityConsoleReadService::class);
    }

    public function test_scheduler_with_no_runs_is_unknown_not_healthy(): void
    {
        $health = $this->read()->overview()['health'];

        $this->assertTrue($health['available']);
        $this->assertSame('unknown', $health['components']['scheduler']['display_status']);
        $this->assertFalse($health['components']['scheduler']['known']);
    }

    public function test_missing_snapshot_is_reported_as_missing_not_fresh(): void
    {
        $health = $this->read()->overview()['health'];

        $this->assertTrue($health['snapshot_missing']);
        $this->assertFalse($health['snapshot_fresh']);
        $this->assertNull($health['snapshot_as_of']);
    }

    public function test_recent_snapshot_is_reported_fresh(): void
    {
        ObservabilityHealthSnapshot::query()->create([
            'scope_type' => ObservabilityHealthSnapshot::SCOPE_APPLICATION,
            'status' => ObservabilityHealthSnapshot::STATUS_HEALTHY,
            'reason_code' => 'no_issues_detected',
            'summary_safe' => 'application health healthy',
            'metrics_json' => [],
            'checked_at' => now(),
            'metadata_json' => [],
        ]);

        $health = $this->read()->overview()['health'];

        $this->assertFalse($health['snapshot_missing']);
        $this->assertTrue($health['snapshot_fresh']);
        $this->assertFalse($health['snapshot_stale']);
        $this->assertNotNull($health['snapshot_as_of']);
    }

    public function test_stale_snapshot_is_reported_stale(): void
    {
        ObservabilityHealthSnapshot::query()->create([
            'scope_type' => ObservabilityHealthSnapshot::SCOPE_APPLICATION,
            'status' => ObservabilityHealthSnapshot::STATUS_HEALTHY,
            'reason_code' => 'no_issues_detected',
            'summary_safe' => 'application health healthy',
            'metrics_json' => [],
            'checked_at' => now()->subSeconds(ObservabilityConsoleReadService::FRESHNESS_TTL_SECONDS + 60),
            'metadata_json' => [],
        ]);

        $health = $this->read()->overview()['health'];

        $this->assertTrue($health['snapshot_stale']);
        $this->assertFalse($health['snapshot_fresh']);
    }

    public function test_observability_page_states_scheduler_unknown_truthfully(): void
    {
        $admin = \App\Models\User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')
            ->get('/admin/observability')
            ->assertOk()
            ->assertSee('Belum ada run tercatat');
    }
}
