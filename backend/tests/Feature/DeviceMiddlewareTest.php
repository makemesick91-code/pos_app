<?php

namespace Tests\Feature;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 10 — the device.registered middleware guards protected Android business
 * APIs by the X-Device-UUID header. A registered ACTIVE device passes; a missing
 * header or a revoked device is blocked. Device registration itself is never
 * blocked by it (a device cannot register if it must already be registered).
 */
class DeviceMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    public function test_protected_api_allowed_with_registered_active_device(): void
    {
        // Default header carries the auto-registered device UUID.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }

    public function test_protected_api_blocked_without_device_header(): void
    {
        $this->flushHeaders();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_NOT_REGISTERED');
    }

    public function test_protected_api_blocked_with_revoked_device(): void
    {
        $this->tenant->registeredDevices()
            ->where('device_uuid', TenantFactory::AUTO_DEVICE_UUID)
            ->update(['status' => RegisteredDevice::STATUS_REVOKED, 'revoked_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_REVOKED');
    }

    public function test_device_registration_endpoint_not_blocked_by_missing_device(): void
    {
        $this->flushHeaders();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', [
                'device_uuid' => 'brand-new-device',
                'platform' => 'ANDROID',
            ])
            ->assertCreated();
    }
}
