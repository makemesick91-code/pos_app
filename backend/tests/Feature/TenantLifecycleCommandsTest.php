<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 25 — the tenant lifecycle governance commands run green and are
 * secret-safe (TLS-R010). enforcement-audit detects guard coverage; go-no-go
 * aggregates the cumulative gate contract.
 */
class TenantLifecycleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-lifecycle:readiness'));
        $this->assertStringContainsString('Decision: GO', Artisan::output());
    }

    public function test_enforcement_audit_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-lifecycle:enforcement-audit', ['--strict' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('lifecycle_guard_coverage', $output);
        $this->assertStringContainsString('Decision: GO', $output);
    }

    public function test_go_no_go_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('tenant-lifecycle:go-no-go'));
        $this->assertStringContainsString('Decision: GO', Artisan::output());
    }

    public function test_suspension_summary_command_reflects_state(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $tenant,
            actor: $admin,
            reason: 'Command summary test.',
            reasonCategory: 'ABUSE',
        );

        Artisan::call('tenant-lifecycle:suspension-summary');
        $this->assertStringContainsString('Active manual suspensions: 1', Artisan::output());
    }

    public function test_go_no_go_json_is_secret_safe(): void
    {
        Artisan::call('tenant-lifecycle:go-no-go', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"decision"', $output);
        foreach (['password', 'secret', 'api_key', 'server_key', 'bearer'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $output);
        }
    }
}
