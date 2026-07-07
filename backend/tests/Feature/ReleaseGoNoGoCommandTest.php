<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 13 — release:go-no-go command contract.
 */
class ReleaseGoNoGoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->artisan('release:go-no-go')
            ->expectsOutputToContain('Release GO/NO-GO')
            ->expectsOutputToContain('Decision:');
    }

    public function test_json_flag_returns_valid_json(): void
    {
        Artisan::call('release:go-no-go', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
    }

    public function test_decision_is_go_when_required_checks_pass(): void
    {
        config(['app.debug' => false]);

        Artisan::call('release:go-no-go', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame('GO', $decoded['decision']);
    }

    public function test_decision_is_no_go_when_critical_check_fails(): void
    {
        config(['app.key' => '']);

        $this->artisan('release:go-no-go')
            ->expectsOutputToContain('Decision: NO-GO')
            ->assertExitCode(1);
    }

    public function test_command_does_not_print_secret_values(): void
    {
        config(['app.key' => 'base64:SUPERSECRETKEYVALUE123456789012345678901234=']);

        Artisan::call('release:go-no-go', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $output);
    }
}
