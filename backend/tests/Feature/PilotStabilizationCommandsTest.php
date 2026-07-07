<?php

namespace Tests\Feature;

use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PilotStabilizationCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defect_summary_json_is_valid(): void
    {
        Artisan::call('pilot:defect-summary', ['--json' => true]);
        $this->assertIsArray(json_decode(Artisan::output(), true));
    }

    public function test_burndown_summary_json_is_valid(): void
    {
        Artisan::call('pilot:burndown-summary', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
    }

    public function test_sla_check_json_is_valid_and_read_only(): void
    {
        Artisan::call('pilot:sla-check', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertSame('read-only', $decoded['mode']);
    }

    public function test_stabilization_go_no_go_json_is_valid(): void
    {
        Artisan::call('pilot:stabilization-go-no-go', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('gates', $decoded);
    }

    public function test_strict_mode_fails_on_no_go(): void
    {
        app(PilotDefectService::class)->create(['title' => 'x', 'area' => 'SYNC', 'severity' => 'BLOCKER']);

        $this->assertSame(1, Artisan::call('pilot:defect-summary', ['--strict' => true]));
        $this->assertSame(1, Artisan::call('pilot:stabilization-go-no-go', ['--strict' => true]));
    }

    public function test_strict_mode_fails_on_watch(): void
    {
        app(PilotDefectService::class)->create(['title' => 'x', 'area' => 'SYNC', 'severity' => 'MAJOR']);

        $this->assertSame(1, Artisan::call('pilot:burndown-summary', ['--strict' => true]));
    }

    public function test_command_output_has_no_secrets(): void
    {
        app(PilotDefectService::class)->create([
            'title' => 'x', 'area' => 'SYNC', 'severity' => 'MINOR',
            'metadata' => ['secret' => 'sk_live_zzz'],
        ]);

        Artisan::call('pilot:burndown-summary', ['--json' => true]);
        $this->assertStringNotContainsString('sk_live_zzz', Artisan::output());
    }
}
