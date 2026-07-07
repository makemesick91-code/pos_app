<?php

namespace Tests\Feature;

use App\Services\Release\ReleaseGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sprint 13 — ReleaseGateService GO / WATCH / NO-GO aggregation.
 */
class ReleaseGateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReleaseGateService
    {
        return app(ReleaseGateService::class);
    }

    private function check(array $report, string $key): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("Check '{$key}' not found in report.");
    }

    public function test_go_when_all_required_checks_pass(): void
    {
        // A clean production-like readiness (debug off) with all contracts intact.
        // Guarantee the storage dirs exist/writable so a fresh CI checkout does
        // not downgrade readiness to WARN over an unprovisioned directory.
        File::ensureDirectoryExists(storage_path('app'));
        File::ensureDirectoryExists(storage_path('logs'));
        config(['app.debug' => false]);

        $report = $this->service()->evaluate();

        $this->assertSame('PASS', $this->check($report, 'required.docs')['status']);
        $this->assertSame('PASS', $this->check($report, 'required.routes')['status']);
        $this->assertSame('PASS', $this->check($report, 'required.commands')['status']);
        $this->assertSame('GO', $report['decision']);
    }

    public function test_missing_required_doc_causes_no_go(): void
    {
        config(['release_readiness.required_docs' => ['docs/this-doc-does-not-exist.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'required.docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_required_route_causes_no_go(): void
    {
        config(['release_readiness.required_routes' => ['api/v1/this/route/is/missing']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'required.routes')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_required_command_causes_no_go(): void
    {
        config(['release_readiness.required_commands' => ['no:such-command']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'required.commands')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_critical_readiness_fail_causes_no_go(): void
    {
        config(['app.key' => '']);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'production.readiness')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_warning_only_causes_watch(): void
    {
        // Debug on in a non-production env is a WARN, not a FAIL.
        config(['app.env' => 'testing', 'app.debug' => true]);

        $report = $this->service()->evaluate();

        $this->assertSame('WATCH', $report['decision']);
    }
}
