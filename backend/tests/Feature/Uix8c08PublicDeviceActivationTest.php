<?php

namespace Tests\Feature;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Services\AndroidRuntime\DeviceActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-8C-08 — device-first, code-authenticated activation (defect UIX8C08-DEF-001).
 *
 * A genuinely fresh device has NO cashier session yet (the app gates
 * ActivationRequired before LoginRequired, UIX8C-R217). Activation MUST therefore
 * succeed WITHOUT a Sanctum token, with the tenant resolved from the single-use
 * code itself (never a client-supplied id, UIX8C-R063) and an unknown code failing
 * closed with no self-provision.
 */
class Uix8c08PublicDeviceActivationTest extends TestCase
{
    use RefreshDatabase;

    private function issueCode(Tenant $tenant, ?int $storeId = null): string
    {
        return app(DeviceActivationService::class)->prepare($tenant, $storeId)['token'];
    }

    public function test_fresh_unauthenticated_device_activates_with_a_valid_code(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'UIX8C08-PUB']);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $code = $this->issueCode($tenant, $store->id);

        // NO actingAs — a truly fresh device with no session.
        $this->postJson('/api/v1/android/device/activate', [
            'activation_token' => $code,
            'device_fingerprint' => 'uix8c08-fingerprint-1',
            'device_uuid' => 'uix8c08-device-1',
            'device_label' => 'Kasir Fisik',
            'app_version' => '0.1.0',
            'installation_id' => 'install-abc-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TenantDeviceActivation::STATUS_ACTIVATED);

        // Device bound to the code's tenant; actor is null (no user yet).
        $activation = TenantDeviceActivation::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(TenantDeviceActivation::STATUS_ACTIVATED, $activation->activation_status);
        $this->assertNull($activation->activated_by_user_id);
        $this->assertTrue(
            RegisteredDevice::query()
                ->where('tenant_id', $tenant->id)
                ->where('device_uuid', 'uix8c08-device-1')
                ->exists(),
        );
    }

    public function test_unknown_code_fails_closed_and_never_self_provisions(): void
    {
        // Even with auto-prepare enabled (the legacy authenticated convenience),
        // the PUBLIC path must never create an activation from an unknown code.
        config(['android_runtime_governance.activation_token.allow_auto_prepare' => true]);

        $this->postJson('/api/v1/android/device/activate', [
            'activation_token' => 'totally-unknown-code-000000',
            'device_fingerprint' => 'uix8c08-fingerprint-x',
            'device_uuid' => 'uix8c08-device-x',
        ])->assertStatus(403);

        $this->assertSame(0, TenantDeviceActivation::query()->count());
        $this->assertSame(0, RegisteredDevice::query()->count());
    }

    public function test_tenant_is_resolved_from_the_code_not_from_client_input(): void
    {
        $tenantA = Tenant::factory()->create(['code' => 'UIX8C08-A']);
        $tenantB = Tenant::factory()->create(['code' => 'UIX8C08-B']);
        $codeA = $this->issueCode($tenantA);

        // Client cannot steer the binding: only tenant A's code is presented, so the
        // device binds to tenant A regardless of any client-supplied hint.
        $this->postJson('/api/v1/android/device/activate', [
            'activation_token' => $codeA,
            'device_fingerprint' => 'uix8c08-fingerprint-a',
            'device_uuid' => 'uix8c08-device-a',
            'store_id' => 99999, // ignored / not trusted
        ])->assertOk();

        // Scope by the uuid we activated (Tenant::factory auto-provisions a device).
        $this->assertTrue(
            RegisteredDevice::query()->where('tenant_id', $tenantA->id)
                ->where('device_uuid', 'uix8c08-device-a')->exists(),
        );
        $this->assertFalse(
            RegisteredDevice::query()->where('tenant_id', $tenantB->id)
                ->where('device_uuid', 'uix8c08-device-a')->exists(),
        );
    }

    public function test_code_is_single_use_replay_by_a_different_device_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'UIX8C08-SU']);
        $code = $this->issueCode($tenant);

        $this->postJson('/api/v1/android/device/activate', [
            'activation_token' => $code,
            'device_fingerprint' => 'uix8c08-su-A',
            'device_uuid' => 'uix8c08-su-A',
        ])->assertOk();

        // A DIFFERENT device replaying the now-bound code is rejected.
        $this->postJson('/api/v1/android/device/activate', [
            'activation_token' => $code,
            'device_fingerprint' => 'uix8c08-su-B',
            'device_uuid' => 'uix8c08-su-B',
        ])->assertStatus(403);

        $this->assertFalse(
            RegisteredDevice::query()->where('device_uuid', 'uix8c08-su-B')->exists(),
        );
    }

    public function test_same_device_reactivation_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'UIX8C08-IDEM']);
        $code = $this->issueCode($tenant);

        $payload = [
            'activation_token' => $code,
            'device_fingerprint' => 'uix8c08-idem-1',
            'device_uuid' => 'uix8c08-idem-1',
        ];
        $this->postJson('/api/v1/android/device/activate', $payload)->assertOk();
        $this->postJson('/api/v1/android/device/activate', $payload)->assertOk();

        // Exactly one device for one logical activation (scope by our uuid; the
        // Tenant::factory auto-provisions an unrelated device).
        $this->assertSame(1, RegisteredDevice::query()->where('device_uuid', 'uix8c08-idem-1')->count());
    }
}
