<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 15 — pilot:field-trial-summary command contract.
 */
class PilotFieldTrialSummaryCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('pilot:field-trial-summary', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_categories(): void
    {
        Artisan::call('pilot:field-trial-summary', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('total_categories', $decoded);
        $this->assertGreaterThanOrEqual(13, $decoded['total_categories']);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:FIELDTRIALSECRETKEY12345678901234567890123=']);

        Artisan::call('pilot:field-trial-summary', ['--json' => true]);

        $this->assertStringNotContainsString('FIELDTRIALSECRETKEY', Artisan::output());
    }
}
