<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 14 — pilot:uat-summary command contract.
 */
class PilotUatSummaryCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('pilot:uat-summary', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_totals(): void
    {
        Artisan::call('pilot:uat-summary', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('total_scenarios', $decoded);
        $this->assertArrayHasKey('required_scenarios', $decoded);
        $this->assertArrayHasKey('blocking_issues', $decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertGreaterThanOrEqual(15, $decoded['total_scenarios']);
    }

    public function test_human_output_includes_total_scenarios(): void
    {
        Artisan::call('pilot:uat-summary');

        $this->assertStringContainsString('Total scenarios:', Artisan::output());
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:UATSECRETKEYVALUE1234567890123456789012345=']);

        Artisan::call('pilot:uat-summary', ['--json' => true]);

        $this->assertStringNotContainsString('UATSECRETKEYVALUE', Artisan::output());
    }
}
