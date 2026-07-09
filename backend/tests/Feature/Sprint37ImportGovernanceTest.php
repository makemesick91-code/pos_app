<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Sprint37ImportGovernanceTest extends TestCase
{
    public function test_all_imp_rules_present_in_config_and_foundation(): void
    {
        for ($i = 1; $i <= 34; $i++) {
            $code = sprintf('IMP-R%03d', $i);
            $this->assertArrayHasKey($code, config('import_governance.rules'));
            $this->assertArrayHasKey($code, config('pos_foundation.import_rules_sprint_37'));
            $this->assertStringContainsString($code, file_get_contents(base_path('../docs/PROJECT_RULES.md')));
        }
    }

    public function test_safe_defaults_and_xlsx_deferred(): void
    {
        $this->assertTrue(config('import_governance.dry_run_default'));
        $this->assertFalse(config('import_governance.tenant_side_import_enabled'));
        $this->assertTrue(config('import_governance.execute_requires_explicit_flag'));
        $this->assertTrue(config('import_governance.execute_requires_reason'));
        $this->assertContains('csv', config('import_governance.supported_formats'));
        $this->assertFalse(config('import_governance.xlsx.supported'));
        $this->assertNotEmpty(config('import_governance.xlsx.deferred_reason'));
    }

    public function test_templates_exist_for_all_import_types(): void
    {
        foreach ((array) config('import_governance.import_types') as $type) {
            $this->assertSame(0, Artisan::call('import:template', ['--type' => $type]));
            $this->assertStringContainsString($type, Artisan::output());
        }
    }

    public function test_governance_commands_pass_and_do_not_leak_secrets(): void
    {
        $this->assertSame(0, Artisan::call('import:governance-audit', ['--json' => true]));
        $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key|sk_live_/i', Artisan::output());

        $this->assertSame(0, Artisan::call('import:go-no-go', ['--json' => true]));
        $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key|sk_live_/i', Artisan::output());
    }

    public function test_no_tenant_public_import_mutation_route_exists(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())->implode("\n");

        $this->assertStringContainsString('api/v1/admin/imports/execute', $routes);
        $this->assertDoesNotMatchRegularExpression('#api/v1/(?!admin/imports).*imports.*POST#', $routes);
    }
}
