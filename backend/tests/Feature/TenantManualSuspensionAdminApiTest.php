<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantManualSuspension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 25 — platform-admin manual tenant suspension / lift governance
 * (TLS-R002, TLS-R005, TLS-R006). Every mutation is authorized, reason-mandatory,
 * idempotent, and audit-logged.
 */
class TenantManualSuspensionAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_suspend_tenant(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [
                'reason' => 'Payment overdue beyond grace.',
                'reason_category' => 'PAYMENT_OVERDUE',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.lifecycle.tenant_status', 'suspended')
            ->assertJsonPath('data.lifecycle.manually_suspended', true);

        $this->assertDatabaseHas('tenant_manual_suspensions', [
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason_category' => 'PAYMENT_OVERDUE',
        ]);
    }

    public function test_non_platform_admin_cannot_suspend_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [
                'reason' => 'trying to self suspend',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');

        $this->assertDatabaseCount('tenant_manual_suspensions', 0);
    }

    public function test_suspend_requires_a_reason(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_suspend_writes_lifecycle_event_and_audit_log(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [
                'reason' => 'Abuse detected.',
                'reason_category' => 'ABUSE',
            ])->assertStatus(201);

        $this->assertDatabaseHas('tenant_lifecycle_events', [
            'tenant_id' => $tenant->id,
            'action' => TenantLifecycleEvent::ACTION_MANUAL_SUSPEND,
            'new_status' => 'suspended',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'tenant.manual_suspend',
            'tenant_id' => $tenant->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_suspension_reason_secrets_are_redacted(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [
                'reason' => 'Fraud review token: abc123secretvalue',
                'reason_category' => 'FRAUD_REVIEW',
            ])->assertStatus(201);

        $suspension = TenantManualSuspension::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertStringNotContainsString('abc123secretvalue', $suspension->reason);
        $this->assertStringContainsString('[REDACTED]', $suspension->reason);
    }

    public function test_platform_admin_can_lift_suspension(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", ['reason' => 'Suspend first.'])
            ->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/lift-suspension", ['reason' => 'Payment received.'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle.tenant_status', 'active')
            ->assertJsonPath('data.lifecycle.manually_suspended', false);

        $this->assertDatabaseHas('tenant_manual_suspensions', [
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_LIFTED,
        ]);

        $this->assertDatabaseHas('tenant_lifecycle_events', [
            'tenant_id' => $tenant->id,
            'action' => TenantLifecycleEvent::ACTION_MANUAL_LIFT,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'tenant.lift_suspension',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_suspend_is_idempotent(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", ['reason' => 'First.'])
            ->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", ['reason' => 'Second.'])
            ->assertOk()
            ->assertJsonPath('already_suspended', true);

        $this->assertDatabaseCount('tenant_manual_suspensions', 1);
    }

    public function test_lift_when_not_suspended_is_safe(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/lift-suspension", ['reason' => 'Nothing to lift.'])
            ->assertOk()
            ->assertJsonPath('not_suspended', true)
            ->assertJsonPath('data.lifecycle.tenant_status', 'active');
    }

    public function test_suspension_summary_reports_counts(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend", [
                'reason' => 'Overdue.',
                'reason_category' => 'PAYMENT_OVERDUE',
            ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/tenant-lifecycle/suspension-summary')
            ->assertOk()
            ->assertJsonPath('data.active_manual_suspensions', 1)
            ->assertJsonPath('data.suspended_tenants', 1);
    }
}
