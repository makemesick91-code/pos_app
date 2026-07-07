<?php

namespace Tests\Feature;

use App\Services\Release\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 13 — ProductionReadinessService structured, secret-safe checks.
 */
class ProductionReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductionReadinessService
    {
        return app(ProductionReadinessService::class);
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

    public function test_report_has_structured_shape(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('overall_status', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertContains($report['overall_status'], ['PASS', 'WARN', 'FAIL']);

        foreach ($report['checks'] as $check) {
            $this->assertArrayHasKey('key', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('message', $check);
            $this->assertArrayHasKey('sensitive', $check);
            $this->assertContains($check['status'], ['PASS', 'WARN', 'FAIL']);
        }
    }

    public function test_app_debug_true_in_production_like_env_fails(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'app.debug')['status']);
        $this->assertSame('FAIL', $report['overall_status']);
    }

    public function test_app_debug_false_passes(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);

        $report = $this->service()->evaluate();

        $this->assertSame('PASS', $this->check($report, 'app.debug')['status']);
    }

    public function test_missing_app_key_fails(): void
    {
        config(['app.key' => '']);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'app.key')['status']);
        $this->assertSame('FAIL', $report['overall_status']);
    }

    public function test_database_and_storage_checks_return_structured_results(): void
    {
        $report = $this->service()->evaluate();

        $this->assertSame('PASS', $this->check($report, 'database.connection')['status']);
        $this->assertSame('PASS', $this->check($report, 'migrations.status')['status']);
        $this->assertSame('PASS', $this->check($report, 'storage.writable')['status']);
        $this->assertSame('PASS', $this->check($report, 'logs.writable')['status']);
    }

    public function test_sensitive_values_are_redacted(): void
    {
        config(['app.key' => 'base64:SUPERSECRETKEYVALUE123456789012345678901234=']);

        $report = $this->service()->evaluate();
        $encoded = json_encode($report);

        $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $encoded);
        $this->assertTrue($this->check($report, 'app.key')['sensitive']);
    }
}
