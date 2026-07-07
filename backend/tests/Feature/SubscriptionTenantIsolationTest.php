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
 * Sprint 10 — the tenant isolation gate for subscriptions/devices: tenant A can
 * never view, revoke, register against, or consume tenant B's device slots, and
 * an identical device_uuid under two tenants never collides.
 */
class SubscriptionTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeB;
    private User $ownerA;
    private RegisteredDevice $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->ownerA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
        // Each tenant auto-registers a device with the same well-known UUID.
        $this->deviceB = $this->tenantB->registeredDevices()
            ->where('device_uuid', TenantFactory::AUTO_DEVICE_UUID)
            ->firstOrFail();
    }

    public function test_tenant_a_cannot_list_tenant_b_devices(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/devices')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($this->deviceB->id));
        $ids->each(function ($id) {
            $this->assertSame(
                $this->tenantA->id,
                (int) RegisteredDevice::query()->whereKey($id)->value('tenant_id'),
            );
        });
    }

    public function test_tenant_a_cannot_revoke_tenant_b_device(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson("/api/v1/devices/{$this->deviceB->id}/revoke")
            ->assertNotFound();

        $this->assertSame(
            RegisteredDevice::STATUS_ACTIVE,
            $this->deviceB->refresh()->status,
        );
    }

    public function test_tenant_a_cannot_register_device_against_tenant_b_store(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/devices/register', [
                'device_uuid' => 'a-device',
                'store_id' => $this->storeB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_same_device_uuid_does_not_collide_across_tenants(): void
    {
        $this->assertSame(
            2,
            RegisteredDevice::query()
                ->where('device_uuid', TenantFactory::AUTO_DEVICE_UUID)
                ->count(),
        );
    }

    public function test_tenant_a_cannot_consume_tenant_b_device_slots(): void
    {
        $beforeB = $this->tenantB->registeredDevices()
            ->where('status', RegisteredDevice::STATUS_ACTIVE)->count();

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/devices/register', ['device_uuid' => 'a-extra', 'platform' => 'ANDROID'])
            ->assertCreated();

        $afterB = $this->tenantB->registeredDevices()
            ->where('status', RegisteredDevice::STATUS_ACTIVE)->count();

        $this->assertSame($beforeB, $afterB);
    }
}
