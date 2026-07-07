<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 16 — pilot:health-summary command contract.
 */
class PilotHealthSummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('pilot:health-summary', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_decision(): void
    {
        Artisan::call('pilot:health-summary', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('total_areas', $decoded);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
    }

    public function test_json_includes_health_area_count(): void
    {
        Artisan::call('pilot:health-summary', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertGreaterThan(0, $decoded['total_areas']);
        $this->assertCount($decoded['total_areas'], $decoded['areas']);
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:HEALTHSUMMARYSECRETKEY12345678901234567890=']);

        Artisan::call('pilot:health-summary', ['--json' => true]);

        $this->assertStringNotContainsString('HEALTHSUMMARYSECRETKEY', Artisan::output());
    }
}
