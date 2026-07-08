<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 26 — the tenant plan governance commands run green and are secret-safe
 * (TPE-R011). enforcement-audit verifies guard coverage + lifecycle precedence;
 * go-no-go aggregates the cumulative gate contract.
 */
class TenantPlanCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-plan:readiness', ['--strict' => true]));
        $this->assertStringContainsString('Decision: GO', Artisan::output());
    }

    public function test_enforcement_audit_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-plan:enforcement-audit', ['--strict' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('entitlement_guard_coverage', $output);
        $this->assertStringContainsString('lifecycle_precedence', $output);
        $this->assertStringContainsString('Decision: GO', $output);
    }

    public function test_entitlement_summary_command_works(): void
    {
        $this->assertSame(0, Artisan::call('tenant-plan:entitlement-summary'));
        $this->assertStringContainsString('Feature Entitlement Summary', Artisan::output());
    }

    public function test_usage_limit_summary_command_works(): void
    {
        $this->assertSame(0, Artisan::call('tenant-plan:usage-limit-summary'));
        $output = Artisan::output();
        $this->assertStringContainsString('Usage Limit Summary', $output);
        $this->assertStringContainsString('products.max', $output);
    }

    public function test_go_no_go_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-plan:go-no-go'));
        $this->assertStringContainsString('Decision: GO', Artisan::output());
    }

    public function test_go_no_go_json_is_secret_safe(): void
    {
        Artisan::call('tenant-plan:go-no-go', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"decision"', $output);
        foreach (['password', 'secret', 'api_key', 'server_key', 'bearer'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $output);
        }
    }

    public function test_lifecycle_go_no_go_still_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-lifecycle:go-no-go'));
        $this->assertStringContainsString('Decision: GO', Artisan::output());
    }
}
