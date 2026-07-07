<?php

namespace Tests\Feature;

use App\Services\Handover\ProductionHandoverService;
use App\Services\Handover\ProductionSignoffService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionHandoverCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closure_check_json_is_valid(): void
    {
        Artisan::call('pilot:closure-check', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
    }

    public function test_handover_summary_json_is_valid(): void
    {
        Artisan::call('production:handover-summary', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('signals', $decoded);
    }

    public function test_signoff_summary_json_is_valid(): void
    {
        Artisan::call('production:signoff-summary', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('required_count', $decoded);
    }

    public function test_handover_go_no_go_json_is_valid(): void
    {
        Artisan::call('production:handover-go-no-go', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('gates', $decoded);
    }

    public function test_strict_mode_fails_on_no_go(): void
    {
        app(PilotDefectService::class)->create(['title' => 'x', 'area' => 'SYNC', 'severity' => 'BLOCKER']);

        $this->assertSame(1, Artisan::call('pilot:closure-check', ['--strict' => true]));
        $this->assertSame(1, Artisan::call('production:handover-go-no-go', ['--strict' => true]));
    }

    public function test_strict_mode_fails_on_watch(): void
    {
        // No package recorded → signoff-summary is WATCH.
        $this->assertSame(1, Artisan::call('production:signoff-summary', ['--strict' => true]));
    }

    public function test_command_output_has_no_secrets(): void
    {
        $package = app(ProductionHandoverService::class)->create([]);
        app(ProductionSignoffService::class)->addSignoff($package, ['signer_role' => 'OWNER', 'decision' => 'APPROVED']);

        Artisan::call('production:handover-go-no-go', ['--json' => true]);
        $out = Artisan::output();
        $this->assertStringNotContainsString((string) config('app.key'), $out);
        $this->assertStringNotContainsString('sk_live', $out);
    }
}
