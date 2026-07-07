<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — admin device list/revoke. Platform admin lists a tenant's devices
 * and revokes them; a revoked device loses business-API access. Ownership is
 * enforced, revoke is idempotent, and every revoke is audit-logged.
 */
class AdminDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_list_tenant_devices(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/devices")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'device_uuid', 'status']], 'meta' => ['tenant_id']])
            ->assertJsonPath('data.0.status', RegisteredDevice::STATUS_ACTIVE);
    }

    public function test_platform_admin_can_revoke_tenant_device_and_it_is_audit_logged(): void
    {
        $tenant = Tenant::factory()->create();
        $device = $tenant->registeredDevices()->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/devices/{$device->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', RegisteredDevice::STATUS_REVOKED);

        $this->assertDatabaseHas('registered_devices', [
            'id' => $device->id,
            'status' => RegisteredDevice::STATUS_REVOKED,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_DEVICE_REVOKED,
            'target_id' => $device->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_device_must_belong_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $deviceB = $tenantB->registeredDevices()->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenantA->id}/devices/{$deviceB->id}/revoke")
            ->assertStatus(404);
    }

    public function test_duplicate_revoke_is_safe(): void
    {
        $tenant = Tenant::factory()->create();
        $device = $tenant->registeredDevices()->first();

        $url = "/api/v1/admin/tenants/{$tenant->id}/devices/{$device->id}/revoke";

        $this->actingAs($this->admin, 'sanctum')->postJson($url)->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson($url)->assertOk()
            ->assertJsonPath('data.status', RegisteredDevice::STATUS_REVOKED);

        // Only one audit entry for the effective revoke; the replay is a no-op.
        $this->assertSame(1, AdminAuditLog::query()
            ->where('action', AdminAuditLog::ACTION_DEVICE_REVOKED)
            ->where('target_id', $device->id)
            ->count());
    }

    public function test_revoked_device_no_longer_passes_device_middleware(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = $tenant->registeredDevices()->first();
        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        // Baseline: business API reachable with the auto device UUID header.
        $this->actingAs($tenantUser, 'sanctum')
            ->withHeader('X-Device-UUID', $device->device_uuid)
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();

        // Admin revokes the device.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/devices/{$device->id}/revoke")
            ->assertOk();

        // Now the same device is blocked by device.registered.
        $this->actingAs($tenantUser, 'sanctum')
            ->withHeader('X-Device-UUID', $device->device_uuid)
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_REVOKED');
    }

    public function test_tenant_user_cannot_revoke_device_via_admin_api(): void
    {
        $tenant = Tenant::factory()->create();
        $device = $tenant->registeredDevices()->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/devices/{$device->id}/revoke")
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }
}
