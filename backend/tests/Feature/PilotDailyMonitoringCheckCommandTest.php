<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 16 — pilot:daily-monitoring-check command contract.
 */
class PilotDailyMonitoringCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('pilot:daily-monitoring-check', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_decision(): void
    {
        Artisan::call('pilot:daily-monitoring-check', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('signals', $decoded);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
    }

    public function test_go_when_ready(): void
    {
        $exit = Artisan::call('pilot:daily-monitoring-check', ['--json' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame('GO', json_decode(Artisan::output(), true)['decision']);
    }

    public function test_strict_mode_fails_on_watch(): void
    {
        // Degrade a non-critical signal to WARN via a temp result file so the
        // decision becomes WATCH; strict mode must then fail.
        $relative = 'backend/storage/framework/testing/pilot-monitoring-watch.json';
        $absolute = base_path('..').'/'.$relative;
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, json_encode(['signals' => ['receipt_printer' => 'WARN']]));
        config(['pilot_monitoring.monitoring_result_file' => $relative]);

        $exit = Artisan::call('pilot:daily-monitoring-check', ['--json' => true, '--strict' => true]);
        @unlink($absolute);

        $this->assertSame(1, $exit);
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:PILOTMONITORINGSECRETKEY123456789012345678=']);

        Artisan::call('pilot:daily-monitoring-check', ['--json' => true]);

        $this->assertStringNotContainsString('PILOTMONITORINGSECRETKEY', Artisan::output());
    }
}
