<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sprint 15 — pilot:deployment-check command contract.
 */
class PilotDeploymentCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('pilot:deployment-check', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_decision(): void
    {
        Artisan::call('pilot:deployment-check', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
    }

    public function test_go_when_ready(): void
    {
        File::ensureDirectoryExists(storage_path('app'));
        File::ensureDirectoryExists(storage_path('logs'));
        config(['app.debug' => false]);

        $exit = Artisan::call('pilot:deployment-check', ['--json' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame('GO', json_decode(Artisan::output(), true)['decision']);
    }

    public function test_strict_mode_fails_on_watch(): void
    {
        config(['app.env' => 'testing', 'app.debug' => true]);

        $exit = Artisan::call('pilot:deployment-check', ['--json' => true, '--strict' => true]);

        $this->assertSame(1, $exit);
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:PILOTDEPLOYSECRETKEY1234567890123456789012=']);

        Artisan::call('pilot:deployment-check', ['--json' => true]);

        $this->assertStringNotContainsString('PILOTDEPLOYSECRETKEY', Artisan::output());
    }
}
