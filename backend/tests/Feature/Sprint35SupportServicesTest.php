<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantBillingInvoice;
use App\Models\TenantEntitlementDecision;
use App\Models\TenantManualSuspension;
use App\Services\SupportOperations\SupportEntitlementViewerService;
use App\Services\SupportOperations\SupportRedactor;
use App\Services\SupportOperations\SupportTenantHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 35 — support service behaviour (health/viewers/redaction).
 */
class Sprint35SupportServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_tenant_is_healthy(): void
    {
        $tenant = Tenant::factory()->create();
        $overview = app(SupportTenantHealthService::class)->overview($tenant);
        $this->assertContains($overview['health_status'], ['healthy', 'watch', 'degraded']);
        $this->assertArrayHasKey('billing', $overview['dimensions']);
    }

    public function test_manual_suspension_forces_critical(): void
    {
        $tenant = Tenant::factory()->create();
        TenantManualSuspension::query()->create([
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'fraud review',
            'reason_category' => 'fraud',
            'effective_at' => now(),
        ]);

        $overview = app(SupportTenantHealthService::class)->overview($tenant->fresh());
        $this->assertSame('critical', $overview['health_status']);
        $this->assertTrue($overview['manual_suspension_active']);
        $this->assertContains('manual_suspension_active', $overview['reason_codes']);
    }

    public function test_unpaid_past_grace_is_blocked(): void
    {
        $tenant = Tenant::factory()->create();
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(60),
        ]);

        $overview = app(SupportTenantHealthService::class)->overview($tenant->fresh());
        $this->assertContains($overview['health_status'], ['blocked', 'critical']);
        $this->assertContains('unpaid_past_grace', $overview['reason_codes']);
    }

    public function test_sync_failure_affects_health(): void
    {
        $tenant = Tenant::factory()->create();
        TenantAndroidSyncBatch::query()->create([
            'tenant_id' => $tenant->id,
            'client_batch_id' => 'b1',
            'idempotency_key' => 'k1',
            'status' => TenantAndroidSyncBatch::STATUS_FAILED,
        ]);

        $overview = app(SupportTenantHealthService::class)->overview($tenant->fresh());
        $this->assertContains('sync_failures_present', $overview['reason_codes']);
    }

    public function test_entitlement_viewer_surfaces_denied_decisions(): void
    {
        $tenant = Tenant::factory()->create();
        TenantEntitlementDecision::query()->create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => null,
            'subject_type' => 'route',
            'subject_id' => 1,
            'entitlement_key' => 'reports.basic',
            'resource_type' => 'export',
            'action' => 'create',
            'decision' => TenantEntitlementDecision::DECISION_DENIED,
            'reason_code' => 'USAGE_LIMIT_EXCEEDED',
            'plan_code' => 'starter',
            'current_usage' => 10,
            'limit_value' => 5,
            'billing_state' => 'unpaid',
            'subscription_state' => 'active',
            'created_at' => now(),
        ]);

        $summary = app(SupportEntitlementViewerService::class)->summary($tenant->id);
        $this->assertTrue($summary['read_only']);
        $this->assertGreaterThanOrEqual(1, count($summary['denied']));
        $this->assertSame('USAGE_LIMIT_EXCEEDED', $summary['denied'][0]['reason_code']);
    }

    public function test_billing_viewer_is_read_only_and_does_not_mutate(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = TenantBillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
        ]);

        $summary = app(\App\Services\SupportOperations\SupportBillingViewerService::class)->summary($tenant->id);
        $this->assertTrue($summary['read_only']);

        // The invoice is untouched (never marked paid).
        $this->assertSame(TenantBillingInvoice::COLLECTION_PENDING, $invoice->fresh()->collection_state);
    }

    public function test_redactor_strips_sensitive_keys_and_text(): void
    {
        $redactor = new SupportRedactor();
        $out = $redactor->redact(['password' => 'p@ss', 'note' => 'ok', 'nested' => ['token' => 'abc']]);
        $this->assertSame('[REDACTED]', $out['password']);
        $this->assertSame('ok', $out['note']);
        $this->assertSame('[REDACTED]', $out['nested']['token']);

        $text = $redactor->redactText('reach me at owner@example.com now');
        $this->assertStringNotContainsString('owner@example.com', (string) $text);
    }
}
