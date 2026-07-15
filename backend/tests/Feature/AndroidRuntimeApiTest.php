<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\AndroidRuntime\DeviceActivationService;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 34 — Android runtime HTTP surface (ADR-R001/R010/R014/R022/R028).
 */
class AndroidRuntimeApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'ADR-API']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    public function test_device_can_be_activated_via_api_without_returning_token(): void
    {
        $code = app(DeviceActivationService::class)->prepare($this->tenant)['token'];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $code,
                'device_fingerprint' => 'fingerprint-abcdef',
                'device_uuid' => 'api-device-1',
                'device_label' => 'Kasir Depan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TenantDeviceActivation::STATUS_ACTIVATED);

        $body = $response->getContent();
        $this->assertStringNotContainsString($code, $body);
        $this->assertStringNotContainsString('fingerprint-abcdef', $body);
    }

    public function test_runtime_policy_is_readable(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/android/runtime/policy')
            ->assertOk()
            ->assertJsonPath('data.offline.require_client_uuid', true)
            ->assertJsonPath('data.sync.batch_idempotency_required', true);
    }

    public function test_sync_batch_is_idempotent_via_api(): void
    {
        $payload = [
            'client_batch_id' => 'api-batch-0001',
            'items' => [[
                'client_item_id' => 'api-item-1',
                'item_type' => 'inventory_snapshot',
                'action' => 'sync_snapshot',
            ]],
        ];

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', TenantFactory::AUTO_DEVICE_UUID)
            ->postJson('/api/v1/android/sync/batch', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.idempotent_replay', false);

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', TenantFactory::AUTO_DEVICE_UUID)
            ->postJson('/api/v1/android/sync/batch', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, \App\Models\TenantAndroidSyncBatch::query()->where('client_batch_id', 'api-batch-0001')->count());
    }

    public function test_admin_runtime_routes_require_platform_admin(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/android-runtime/devices')
            ->assertForbidden();
    }

    public function test_platform_admin_can_list_and_revoke_devices(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'activation-token-9999', 'fp-admin', 'admin-dev-1', 'Kasir', $this->user);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/android-runtime/devices')
            ->assertOk()
            ->assertJsonStructure(['data', 'summary']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/android-runtime/devices/{$activation->id}/revoke", ['reason' => 'support'])
            ->assertOk()
            ->assertJsonPath('data.status', TenantDeviceActivation::STATUS_REVOKED);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ANDROID_DEVICE_REVOKED',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
