<?php

namespace Tests\Feature;

use App\Models\ObservabilitySchedulerRun;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\Observability\InfrastructureHealthCheckService;
use App\Services\Observability\ObservabilityHealthService;
use App\Services\Observability\QueueHealthService;
use App\Services\Observability\SchedulerHealthService;
use App\Services\Observability\TenantRuntimeProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprint 36 — core observability services (OBS-R005..R012/R020).
 */
class Sprint36ObservabilityServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_infrastructure_check_is_ok_and_leaks_no_credentials(): void
    {
        $result = app(InfrastructureHealthCheckService::class)->check();

        $this->assertSame('ok', $result['status']);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertArrayHasKey('cache', $result['checks']);
        $this->assertArrayHasKey('storage', $result['checks']);

        $json = json_encode($result);
        // Only names/booleans — never a DSN, password, or absolute path.
        $this->assertDoesNotMatchRegularExpression('/password|secret|:memory:|\/home\/|DB_PASSWORD/i', (string) $json);
    }

    public function test_database_check_reports_connection_name_not_dsn(): void
    {
        $db = app(InfrastructureHealthCheckService::class)->database();
        $this->assertSame('ok', $db['status']);
        $this->assertArrayHasKey('connection', $db);
        $this->assertArrayNotHasKey('password', $db);
        $this->assertArrayNotHasKey('dsn', $db);
    }

    public function test_cache_check_reports_store_name_only(): void
    {
        $cache = app(InfrastructureHealthCheckService::class)->cache();
        $this->assertSame('ok', $cache['status']);
        $this->assertArrayHasKey('store', $cache);
    }

    public function test_queue_health_is_healthy_on_empty_queue(): void
    {
        $summary = app(QueueHealthService::class)->summary();
        $this->assertSame('healthy', $summary['status']);
        $this->assertSame(0, $summary['metrics']['pending_jobs']);
        $this->assertSame(0, $summary['metrics']['failed_jobs']);
    }

    public function test_queue_health_flags_failed_jobs(): void
    {
        for ($i = 0; $i < 12; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => 'uuid-'.$i,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\SendReceipt']),
                'exception' => 'RuntimeException: boom',
                'failed_at' => now(),
            ]);
        }
        $summary = app(QueueHealthService::class)->summary();
        $this->assertContains($summary['status'], ['watch', 'degraded']);
        $this->assertSame(12, $summary['metrics']['failed_jobs']);
    }

    public function test_scheduler_health_healthy_with_no_runs(): void
    {
        $summary = app(SchedulerHealthService::class)->summary();
        $this->assertSame('healthy', $summary['status']);
        $this->assertContains('no_runs_recorded', $summary['reason_codes']);
    }

    public function test_scheduler_health_detects_fresh_run(): void
    {
        $svc = app(SchedulerHealthService::class);
        $run = $svc->recordStart('demo:command');
        $svc->recordComplete($run, 0);

        $summary = $svc->summary();
        $this->assertSame('healthy', $summary['status']);
        $this->assertCount(1, $summary['commands']);
    }

    public function test_scheduler_health_detects_stale_run(): void
    {
        ObservabilitySchedulerRun::query()->create([
            'command_name' => 'stale:command',
            'status' => ObservabilitySchedulerRun::STATUS_COMPLETED,
            'started_at' => Carbon::now()->subDays(2),
            'completed_at' => Carbon::now()->subDays(2),
            'duration_ms' => 10,
            'exit_code' => 0,
        ]);

        $summary = app(SchedulerHealthService::class)->summary();
        $this->assertContains('command_stale', $summary['reason_codes']);
    }

    public function test_scheduler_health_detects_stuck_run(): void
    {
        ObservabilitySchedulerRun::query()->create([
            'command_name' => 'stuck:command',
            'status' => ObservabilitySchedulerRun::STATUS_STARTED,
            'started_at' => Carbon::now()->subDays(1),
        ]);

        $summary = app(SchedulerHealthService::class)->summary();
        $this->assertSame('degraded', $summary['status']);
        $this->assertContains('command_stuck', $summary['reason_codes']);
    }

    public function test_application_health_overview_is_deterministic_and_explainable(): void
    {
        $overview = app(ObservabilityHealthService::class)->overview();
        $this->assertArrayHasKey('status', $overview);
        $this->assertArrayHasKey('reason_codes', $overview);
        $this->assertArrayHasKey('components', $overview);
        $this->assertContains($overview['status'], ['healthy', 'watch', 'degraded', 'blocked', 'critical']);
    }

    public function test_health_snapshot_stores_aggregate_metrics_only(): void
    {
        $snapshot = app(ObservabilityHealthService::class)->snapshot();
        $this->assertSame('application', $snapshot->scope_type);
        $json = json_encode($snapshot->metrics_json);
        $this->assertDoesNotMatchRegularExpression('/password|secret|token/i', (string) $json);
    }

    public function test_tenant_probe_healthy_for_active_tenant(): void
    {
        $tenant = $this->tenant('OBS-HEALTHY');
        $probe = app(TenantRuntimeProbeService::class)->probe($tenant);
        $this->assertSame('healthy', $probe['health_status']);
        $this->assertSame('sprint35_support_tenant_health', $probe['source']);
    }

    public function test_tenant_probe_critical_for_suspended_tenant(): void
    {
        $tenant = $this->tenant('OBS-SUSPENDED');
        $this->suspend($tenant);

        $probe = app(TenantRuntimeProbeService::class)->probe($tenant->fresh());
        $this->assertSame('critical', $probe['health_status']);
        $this->assertTrue($probe['manual_suspension_active']);
        $this->assertContains('manual_suspension_active', $probe['reason_codes']);
    }

    public function test_tenant_probe_is_tenant_isolated(): void
    {
        $a = $this->tenant('OBS-ISO-A');
        $b = $this->tenant('OBS-ISO-B');
        $this->suspend($b);

        $this->assertSame('healthy', app(TenantRuntimeProbeService::class)->probe($a->fresh())['health_status']);
        $this->assertSame('critical', app(TenantRuntimeProbeService::class)->probe($b->fresh())['health_status']);
    }

    public function test_degraded_count_counts_only_degraded_or_worse(): void
    {
        $this->tenant('OBS-DC-A');
        $suspended = $this->tenant('OBS-DC-B');
        $this->suspend($suspended);

        $this->assertSame(1, app(TenantRuntimeProbeService::class)->degradedCount());
    }

    private function suspend(Tenant $tenant): void
    {
        TenantManualSuspension::query()->create([
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'test suspension',
            'reason_category' => 'nonpayment',
            'effective_at' => now(),
        ]);
    }

    private function tenant(string $code): Tenant
    {
        $tenant = Tenant::factory()->create(['code' => $code]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        return $tenant;
    }
}
