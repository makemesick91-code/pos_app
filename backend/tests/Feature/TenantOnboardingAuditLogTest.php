<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — onboarding, demo seed, and demo reset each write an admin audit
 * log, and no audit record ever stores the owner password or a plain secret.
 */
class TenantOnboardingAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
        $this->plan = SubscriptionPlan::factory()->starter()->create();
    }

    public function test_onboarding_and_demo_actions_are_audit_logged_without_secrets(): void
    {
        // Onboarding.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', [
                'onboarding_reference' => 'audit-001',
                'tenant_name' => 'Audit Tenant',
                'tenant_code' => 'audit-tenant',
                'store_name' => 'Audit Store',
                'owner_name' => 'Audit Owner',
                'owner_email' => 'audit.owner@example.test',
                'owner_password' => 'super-secret-pass',
                'subscription_plan_id' => $this->plan->id,
                'subscription_status' => 'TRIAL',
                'demo_data_enabled' => true,
            ])
            ->assertCreated();

        $tenant = Tenant::query()->where('code', 'audit-tenant')->firstOrFail();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_TENANT_ONBOARDED,
            'tenant_id' => $tenant->id,
        ]);

        // Demo seed on an existing tenant.
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'code' => 'AX']);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/demo-data", ['store_id' => $store->id])
            ->assertCreated();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_DEMO_DATA_SEEDED,
            'tenant_id' => $tenant->id,
        ]);

        // Demo reset.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/demo-data/reset", ['confirm_demo_reset' => true])
            ->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_DEMO_DATA_RESET,
            'tenant_id' => $tenant->id,
        ]);

        // No audit record may contain the password.
        foreach (AdminAuditLog::query()->get() as $log) {
            $encoded = json_encode([
                $log->before_values,
                $log->after_values,
                $log->metadata,
            ]);
            $this->assertStringNotContainsString('super-secret-pass', (string) $encoded);
        }
    }
}
