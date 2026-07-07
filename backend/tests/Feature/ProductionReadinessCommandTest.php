<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 13 — production:readiness-check command contract.
 */
class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered_and_outputs_status_structure(): void
    {
        config(['app.debug' => false]);

        $this->artisan('production:readiness-check')
            ->expectsOutputToContain('Production Readiness Check')
            ->expectsOutputToContain('Overall:')
            ->assertExitCode(0);
    }

    public function test_json_flag_returns_valid_json(): void
    {
        Artisan::call('production:readiness-check', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('overall_status', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
    }

    public function test_command_does_not_print_secret_values(): void
    {
        config(['app.key' => 'base64:SUPERSECRETKEYVALUE123456789012345678901234=']);

        Artisan::call('production:readiness-check', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $output);
    }

    public function test_strict_mode_fails_on_warning(): void
    {
        // Debug on in testing env yields overall WARN.
        config(['app.env' => 'testing', 'app.debug' => true]);

        $this->artisan('production:readiness-check', ['--strict' => true])
            ->assertExitCode(1);
    }

    public function test_fails_on_dangerous_production_setting(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $this->artisan('production:readiness-check')
            ->assertExitCode(1);
    }
}
