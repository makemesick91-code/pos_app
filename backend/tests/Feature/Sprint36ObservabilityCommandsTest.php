<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\ObservabilityAnomalyEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 36 — observability console commands (OBS-R004/R020/R029/R032).
 */
class Sprint36ObservabilityCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:health', ['--json' => true]));
    }

    public function test_infra_check_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:infra-check', ['--json' => true]));
    }

    public function test_queue_health_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:queue-health', ['--json' => true]));
    }

    public function test_failed_jobs_command_runs_and_is_redacted(): void
    {
        $this->assertSame(0, Artisan::call('observability:failed-jobs', ['--json' => true]));
        $this->assertDoesNotMatchRegularExpression('/password|secret|sk_live_/i', Artisan::output());
    }

    public function test_scheduler_health_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:scheduler-health', ['--json' => true]));
    }

    public function test_tenant_probe_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:tenant-probe', ['--json' => true]));
    }

    public function test_anomaly_scan_dry_run_does_not_persist(): void
    {
        $tenant = $this->tenant();
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);

        $this->assertSame(0, Artisan::call('observability:anomaly-scan', ['--json' => true]));
        $this->assertSame(0, ObservabilityAnomalyEvent::query()->count());
    }

    public function test_anomaly_scan_execute_persists_events(): void
    {
        $tenant = $this->tenant();
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);

        $this->assertSame(0, Artisan::call('observability:anomaly-scan', ['--execute' => true, '--json' => true]));
        $this->assertGreaterThan(0, ObservabilityAnomalyEvent::query()->count());
    }

    public function test_metrics_summary_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:metrics-summary', ['--json' => true]));
    }

    public function test_alert_suggestions_command_runs(): void
    {
        $this->assertSame(0, Artisan::call('observability:alert-suggestions', ['--json' => true]));
    }

    public function test_alert_suggestions_generate_creates_suggestions_only(): void
    {
        $tenant = $this->tenant();
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);
        Artisan::call('observability:anomaly-scan', ['--execute' => true]);
        $this->assertSame(0, Artisan::call('observability:alert-suggestions', ['--generate' => true, '--json' => true]));
        // No support incident auto-created.
        $this->assertDatabaseCount('tenant_support_incidents', 0);
    }

    public function test_governance_audit_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('observability:governance-audit'));
    }

    public function test_go_no_go_command_is_go(): void
    {
        $this->assertSame(0, Artisan::call('observability:go-no-go', ['--strict' => true]));
    }

    public function test_command_output_has_no_secret_or_pii(): void
    {
        foreach (['observability:health', 'observability:metrics-summary', 'observability:go-no-go'] as $command) {
            Artisan::call($command, ['--json' => true]);
            $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key|sk_live_/i', Artisan::output());
        }
    }

    private function tenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'role' => User::ROLE_TENANT_OWNER]);

        return $tenant;
    }
}
