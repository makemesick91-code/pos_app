<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Services\AndroidRuntime\DeviceRevocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-8C-07 — the server-authoritative device-status poll
 * (GET /api/v1/android/device/status), UIX8C-R221/R220/R223/R227.
 *
 * The endpoint is READ-ONLY, tenant-scoped, reachable by a revoked device (so it
 * can learn why), and never leaks a token/fingerprint/installation hash.
 */
class DeviceStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'UIX8C07-STATUS']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_CASHIER,
        ]);
    }

    public function test_unregistered_device_reports_not_activated(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', 'never-activated-uuid')
            ->getJson('/api/v1/android/device/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'not_activated')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.revoked', false)
            ->assertJsonPath('data.tenant.name', $this->tenant->name);
    }

    public function test_activated_device_reports_active(): void
    {
        $this->activate('status-active-uuid');

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', 'status-active-uuid')
            ->getJson('/api/v1/android/device/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.revoked', false);
    }

    public function test_revoked_device_reports_revoked_with_reason_and_is_reachable(): void
    {
        $activation = $this->activate('status-revoked-uuid');
        app(DeviceRevocationService::class)->revoke(
            $activation->refresh(),
            User::factory()->platformAdmin()->create(),
            'Terminal hilang',
        );

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', 'status-revoked-uuid')
            ->getJson('/api/v1/android/device/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.revoked', true)
            ->assertJsonPath('data.revocation_reason', 'Terminal hilang');

        // No secret material leaks (UIX8C-R227/R246).
        $body = $response->getContent();
        $this->assertStringNotContainsString('token_hash', $body);
        $this->assertStringNotContainsString('installation_id_hash', $body);
        $this->assertStringNotContainsString('fingerprint', $body);
    }

    public function test_status_is_tenant_scoped_and_does_not_leak_another_tenant_device(): void
    {
        // Another tenant activates a device with a colliding UUID label.
        $otherTenant = Tenant::factory()->create(['code' => 'UIX8C07-OTHER']);
        $otherStore = Store::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'store_id' => $otherStore->id,
            'role' => User::ROLE_CASHIER,
        ]);
        $otherCode = app(DeviceActivationService::class)->prepare($otherTenant)['token'];
        $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $otherCode,
                'device_fingerprint' => 'other-fingerprint-1',
                'device_uuid' => 'shared-uuid',
                'device_label' => 'Other',
            ])->assertOk();

        // Our tenant polling the SAME uuid must not see the other tenant's device.
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Device-UUID', 'shared-uuid')
            ->getJson('/api/v1/android/device/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'not_activated')
            ->assertJsonPath('data.tenant.name', $this->tenant->name);
    }

    private function activate(string $uuid): TenantDeviceActivation
    {
        $code = app(DeviceActivationService::class)->prepare($this->tenant)['token'];
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $code,
                'device_fingerprint' => 'status-fp-'.$uuid,
                'device_uuid' => $uuid,
                'device_label' => 'Kasir '.$uuid,
                'app_version' => '0.1.0',
                'installation_id' => 'install-'.$uuid,
            ])->assertOk();

        return TenantDeviceActivation::query()
            ->where('tenant_id', $this->tenant->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
