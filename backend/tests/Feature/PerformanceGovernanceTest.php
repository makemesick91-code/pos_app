<?php

namespace Tests\Feature;

use App\Models\PerformanceBenchmarkRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_perf_rules_are_registered_in_canonical_files(): void
    {
        for ($i = 1; $i <= 36; $i++) {
            $rule = 'PERF-R'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $this->assertArrayHasKey($rule, config('performance_governance.rules'));
            $this->assertStringContainsString($rule, file_get_contents(base_path('config/pos_foundation.php')));
            $this->assertStringContainsString($rule, file_get_contents(base_path('../docs/PROJECT_RULES.md')));
        }
    }

    public function test_ci_profile_is_safe_and_heavy_is_not_default(): void
    {
        $this->assertSame('ci_smoke', config('performance_governance.default_profile'));
        $this->assertArrayHasKey('manual_heavy', config('performance_governance.profiles'));
        $this->assertLessThanOrEqual(3, config('performance_governance.profiles.ci_smoke.tenant_count'));
    }

    public function test_fixture_build_is_dry_run_by_default(): void
    {
        $this->artisan('performance:fixture-build', ['--profile' => 'ci_smoke'])->assertExitCode(0);
        $this->assertDatabaseCount('performance_benchmark_runs', 0);
    }

    public function test_performance_run_records_steps_and_passes_thresholds(): void
    {
        $this->artisan('performance:run', ['--profile' => 'ci_smoke'])->assertExitCode(0);
        $run = PerformanceBenchmarkRun::query()->firstOrFail();
        $this->assertSame('pass', $run->threshold_status);
        $this->assertSame(10, $run->steps()->count());
        $this->assertSame(0, $run->steps()->sum('duplicate_count'));
    }

    public function test_threshold_check_fails_closed_on_regression(): void
    {
        $run = PerformanceBenchmarkRun::query()->create([
            'profile' => 'ci_smoke',
            'status' => 'completed',
            'benchmark_key' => 'regression',
            'duration_ms' => 999999999,
            'threshold_status' => 'warn',
        ]);
        $this->artisan('performance:threshold-check', ['--run' => $run->id])->assertExitCode(1);
        $this->assertSame('fail', $run->refresh()->threshold_status);
    }

    public function test_query_review_does_not_write_without_execute(): void
    {
        $this->artisan('performance:query-review')->assertExitCode(0);
        $this->assertDatabaseCount('performance_query_reviews', 0);
        $this->artisan('performance:query-review', ['--execute' => true])->assertExitCode(0);
        $this->assertDatabaseCount('performance_query_reviews', 13);
    }

    public function test_admin_performance_routes_require_platform_admin_and_reason_for_mutation(): void
    {
        $this->getJson('/api/v1/admin/performance/profiles')->assertUnauthorized();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/performance/profiles')->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/performance/runs', ['profile' => 'ci_smoke'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/performance/runs', ['profile' => 'ci_smoke', 'reason_code' => 'ci_smoke'])->assertCreated();
    }

    public function test_go_no_go_requires_smoke_run_but_not_deploy_by_default(): void
    {
        $this->artisan('performance:go-no-go')->assertExitCode(1);
        $this->artisan('performance:run', ['--profile' => 'ci_smoke'])->assertExitCode(0);
        $this->artisan('performance:go-no-go')->assertExitCode(0);
        $this->artisan('performance:go-no-go', ['--require-deploy' => true])->assertExitCode(1);
    }
}
