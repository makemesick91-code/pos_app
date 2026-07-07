<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 16 — hypercare:issue-triage command contract.
 */
class HypercareIssueTriageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('hypercare:issue-triage', Artisan::all());
    }

    public function test_json_output_is_valid_and_has_decision(): void
    {
        Artisan::call('hypercare:issue-triage', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertArrayHasKey('open_blocker', $decoded);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
    }

    public function test_json_includes_open_issue_counts(): void
    {
        Artisan::call('hypercare:issue-triage', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('open_blocker', $decoded);
        $this->assertArrayHasKey('open_critical', $decoded);
        $this->assertArrayHasKey('open_major', $decoded);
    }

    public function test_output_does_not_contain_secrets(): void
    {
        config(['app.key' => 'base64:HYPERCARESECRETKEY1234567890123456789012345=']);

        Artisan::call('hypercare:issue-triage', ['--json' => true]);

        $this->assertStringNotContainsString('HYPERCARESECRETKEY', Artisan::output());
    }
}
