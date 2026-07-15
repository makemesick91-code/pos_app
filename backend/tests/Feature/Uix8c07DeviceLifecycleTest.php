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
 * UIX-8C-07 — device lifecycle metadata capture + redaction + revocation reason
 * (UIX8C-R218/R219/R227/R234). The installation id is stored ONLY as a hash; the
 * raw value is never persisted, and no hash is exposed in the safe output.
 */
class Uix8c07DeviceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'UIX8C07-LIFE']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_CASHIER,
        ]);
    }

    public function test_activation_captures_app_version_and_hashes_installation_id(): void
    {
        $rawInstallationId = 'installation-uuid-raw-value';

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => 'life-token-1234',
                'device_fingerprint' => 'life-fingerprint-1',
                'device_uuid' => 'life-device-1',
                'device_label' => 'Kasir',
                'app_version' => '0.1.0',
                'installation_id' => $rawInstallationId,
            ])->assertOk();

        $activation = TenantDeviceActivation::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('0.1.0', $activation->app_version);
        // The raw installation id is never persisted — only its hash.
        $this->assertNotNull($activation->installation_id_hash);
        $this->assertNotSame($rawInstallationId, $activation->installation_id_hash);
        $expectedHash = app(DeviceActivationService::class)->hashFingerprint($rawInstallationId);
        $this->assertSame($expectedHash, $activation->installation_id_hash);

        // The safe array exposes app_version but never the installation hash.
        $safe = $activation->toSafeArray();
        $this->assertSame('0.1.0', $safe['app_version']);
        $this->assertArrayNotHasKey('installation_id_hash', $safe);
        $this->assertArrayNotHasKey('activation_token_hash', $safe);
        $this->assertArrayNotHasKey('device_fingerprint_hash', $safe);
    }

    public function test_revocation_records_a_human_safe_reason(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => 'life-token-revoke',
                'device_fingerprint' => 'life-fingerprint-revoke',
                'device_uuid' => 'life-device-revoke',
            ])->assertOk();

        $activation = TenantDeviceActivation::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        app(DeviceRevocationService::class)->revoke(
            $activation,
            User::factory()->platformAdmin()->create(),
            'Perangkat diganti',
        );

        $activation->refresh();
        $this->assertTrue($activation->isRevoked());
        $this->assertSame('Perangkat diganti', $activation->revocation_reason);
        $this->assertSame('Perangkat diganti', $activation->toSafeArray()['revocation_reason']);
    }

    public function test_revocation_without_reason_stores_a_safe_default(): void
    {
        $activation = TenantDeviceActivation::query()->create([
            'tenant_id' => $this->tenant->id,
            'activation_status' => TenantDeviceActivation::STATUS_ACTIVATED,
            'activated_at' => now(),
        ]);

        app(DeviceRevocationService::class)->revoke(
            $activation,
            User::factory()->platformAdmin()->create(),
        );

        $this->assertSame(
            'Perangkat ini telah dinonaktifkan oleh admin.',
            $activation->refresh()->revocation_reason,
        );
    }
}
