<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — admin audit log read API + sanitization. Platform admin only. The
 * stored snapshots never contain secrets or raw payment gateway payloads.
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_audit_log_records_actor_action_target_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $log = AdminAuditLog::factory()->create([
            'actor_user_id' => $this->admin->id,
            'action' => AdminAuditLog::ACTION_SUBSCRIPTION_ASSIGNED,
            'target_type' => AdminAuditLog::TARGET_SUBSCRIPTION,
            'target_id' => 42,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'id' => $log->id,
            'actor_user_id' => $this->admin->id,
            'action' => AdminAuditLog::ACTION_SUBSCRIPTION_ASSIGNED,
            'target_type' => AdminAuditLog::TARGET_SUBSCRIPTION,
            'target_id' => 42,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_platform_admin_can_list_and_show_audit_logs(): void
    {
        $log = AdminAuditLog::factory()->create(['actor_user_id' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'actor_user_id', 'action', 'target_type']], 'meta']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_audit_logs_can_be_filtered_by_action_and_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        AdminAuditLog::factory()->create([
            'actor_user_id' => $this->admin->id,
            'action' => AdminAuditLog::ACTION_DEVICE_REVOKED,
            'tenant_id' => $tenant->id,
        ]);
        AdminAuditLog::factory()->create([
            'actor_user_id' => $this->admin->id,
            'action' => AdminAuditLog::ACTION_PLAN_CREATED,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs?action=' . AdminAuditLog::ACTION_DEVICE_REVOKED)
            ->assertOk();

        $actions = array_column($response->json('data'), 'action');
        $this->assertNotEmpty($actions);
        $this->assertSame([AdminAuditLog::ACTION_DEVICE_REVOKED], array_values(array_unique($actions)));
    }

    public function test_tenant_user_cannot_list_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }

    public function test_audit_logger_sanitizes_secrets_and_raw_payloads(): void
    {
        $logger = app(AdminAuditLogger::class);

        $sanitized = $logger->sanitize([
            'status' => 'ACTIVE',
            'password' => 'super-secret',
            'server_key' => 'MID-XXX',
            'gateway_payload' => ['raw' => 'stuff'],
            'signature' => 'abc',
            'plan_code' => 'starter',
            'nested' => ['api_key' => 'k', 'safe' => 'ok'],
        ]);

        $this->assertSame('ACTIVE', $sanitized['status']);
        $this->assertSame('starter', $sanitized['plan_code']);
        $this->assertArrayNotHasKey('password', $sanitized);
        $this->assertArrayNotHasKey('server_key', $sanitized);
        $this->assertArrayNotHasKey('gateway_payload', $sanitized);
        $this->assertArrayNotHasKey('signature', $sanitized);
        $this->assertArrayNotHasKey('api_key', $sanitized['nested']);
        $this->assertSame('ok', $sanitized['nested']['safe']);
    }

    public function test_audit_log_resource_does_not_expose_secret_keys(): void
    {
        $log = AdminAuditLog::factory()->create([
            'actor_user_id' => $this->admin->id,
            'after_values' => ['status' => 'ACTIVE', 'plan_code' => 'pro'],
        ]);

        $raw = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/audit-logs/{$log->id}")
            ->assertOk()
            ->getContent();

        foreach (['password', 'server_key', 'secret', 'gateway_payload', 'signature'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $raw);
        }
    }
}
