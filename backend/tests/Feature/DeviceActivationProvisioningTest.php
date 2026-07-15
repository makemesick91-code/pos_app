<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * UIX-8C-07 — the single-use, short-lived device-activation code provisioning CLI
 * (device:provision-activation), UIX8C-R217/R221/R246.
 *
 * The command wires DeviceActivationService::prepare(): the raw code is shown once
 * (never stored/logged in plaintext — only its hash is persisted), and it is
 * single-use (activate() consumes it and flips the record to ACTIVATED).
 */
class DeviceActivationProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'UIX8C07-PROV']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_CASHIER,
        ]);
    }

    public function test_command_issues_a_single_use_code_that_activates_a_device(): void
    {
        $code = $this->provisionCode();

        // The pending activation is stored by HASH only — never the raw code.
        $pending = TenantDeviceActivation::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(TenantDeviceActivation::STATUS_PENDING, $pending->activation_status);
        $this->assertNotSame($code, $pending->activation_token_hash);
        $this->assertNotNull($pending->activation_token_hash);

        // The issued code activates the device.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $code,
                'device_fingerprint' => 'prov-fingerprint-1',
                'device_uuid' => 'prov-device-1',
                'device_label' => 'Kasir Provision',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TenantDeviceActivation::STATUS_ACTIVATED);
    }

    public function test_issued_code_is_single_use_and_replay_by_a_different_device_is_rejected(): void
    {
        // With issued codes the secure posture disables self-provisioning
        // auto-prepare, so ONLY the genuine issued (hashed) code can activate.
        config(['android_runtime_governance.activation_token.allow_auto_prepare' => false]);

        $code = $this->provisionCode();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $code,
                'device_fingerprint' => 'prov-fingerprint-A',
                'device_uuid' => 'prov-device-A',
            ])->assertOk();

        // A DIFFERENT device replaying the now-consumed code is rejected
        // (bound to the first fingerprint) — the code is single-use and never
        // activates a second device.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/android/device/activate', [
                'activation_token' => $code,
                'device_fingerprint' => 'prov-fingerprint-B',
                'device_uuid' => 'prov-device-B',
            ])->assertStatus(403);

        // Single-use guarantee: the replaying device (fingerprint/uuid B) was
        // NEVER registered — the consumed code cannot activate a second device
        // (UIX8C-R217/R221).
        $this->assertFalse(
            \App\Models\RegisteredDevice::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('device_uuid', 'prov-device-B')
                ->exists(),
        );
    }

    public function test_command_rejects_unknown_tenant(): void
    {
        $exit = Artisan::call('device:provision-activation', ['--tenant' => 'NOPE']);
        $this->assertSame(1, $exit);
    }

    private function provisionCode(): string
    {
        $exit = Artisan::call('device:provision-activation', [
            '--tenant' => $this->tenant->code,
            '--ttl' => 120,
        ]);
        $this->assertSame(0, $exit);

        $output = Artisan::output();
        $this->assertStringContainsString('ACTIVATION CODE:', $output);
        preg_match('/ACTIVATION CODE:\s*(\S+)/', $output, $m);
        $this->assertNotEmpty($m[1] ?? null, 'activation code not found in command output');

        return $m[1];
    }
}
