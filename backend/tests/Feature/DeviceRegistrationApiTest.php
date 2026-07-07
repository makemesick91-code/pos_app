<?php

namespace Tests\Feature;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 10 — device registration + heartbeat. Registration is tenant-owned and
 * backend enforced; duplicate UUIDs replay the existing device; heartbeats
 * refresh last_seen_at; revoked devices are rejected; a store must belong to the
 * tenant.
 */
class DeviceRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);
    }

    public function test_tenant_user_can_register_android_device(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', [
                'device_uuid' => 'device-new-1',
                'device_name' => 'Samsung A12 Kasir 1',
                'platform' => 'ANDROID',
                'app_version' => '1.0.0',
                'store_id' => $this->store->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.device_uuid', 'device-new-1')
            ->assertJsonPath('data.status', RegisteredDevice::STATUS_ACTIVE)
            ->assertJsonPath('meta.existing_device', false);

        $this->assertDatabaseHas('registered_devices', [
            'tenant_id' => $this->tenant->id,
            'device_uuid' => 'device-new-1',
            'status' => RegisteredDevice::STATUS_ACTIVE,
        ]);
    }

    public function test_duplicate_uuid_returns_existing_device_without_new_row(): void
    {
        $payload = [
            'device_uuid' => 'device-dup',
            'device_name' => 'Kasir 1',
            'platform' => 'ANDROID',
        ];

        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', $payload)
            ->assertOk()
            ->assertJsonPath('meta.existing_device', true)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            RegisteredDevice::query()->where('device_uuid', 'device-dup')->count(),
        );
    }

    public function test_heartbeat_updates_last_seen_at(): void
    {
        $device = RegisteredDevice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_uuid' => 'device-hb',
            'last_seen_at' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/heartbeat', [
                'device_uuid' => 'device-hb',
                'app_version' => '1.1.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.device_uuid', 'device-hb');

        $device->refresh();
        $this->assertTrue($device->last_seen_at->greaterThan(now()->subMinute()));
        $this->assertSame('1.1.0', $device->app_version);
    }

    public function test_revoked_device_heartbeat_is_rejected(): void
    {
        RegisteredDevice::factory()->revoked()->create([
            'tenant_id' => $this->tenant->id,
            'device_uuid' => 'device-revoked',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/heartbeat', [
                'device_uuid' => 'device-revoked',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_REVOKED');
    }

    public function test_store_id_must_belong_to_tenant(): void
    {
        $otherStore = Store::factory()->create(['code' => 'OTHER']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', [
                'device_uuid' => 'device-badstore',
                'store_id' => $otherStore->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
