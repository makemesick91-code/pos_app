<?php

namespace Tests\Feature;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\AndroidRuntime\AndroidRuntimeException;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Services\AndroidRuntime\DeviceRevocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 34 — DeviceActivationService (ADR-R002..R007, R026, R027).
 */
class AndroidDeviceActivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'ADR-A']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function service(): DeviceActivationService
    {
        return app(DeviceActivationService::class);
    }

    public function test_valid_activation_creates_activation_and_device(): void
    {
        $activation = $this->service()->activate($this->tenant, Str::random(40), 'fingerprint-1', 'uuid-1', 'Kasir 1', $this->owner);

        $this->assertSame(TenantDeviceActivation::STATUS_ACTIVATED, $activation->activation_status);
        $this->assertNotNull($activation->device_id);
        $this->assertDatabaseHas('registered_devices', [
            'tenant_id' => $this->tenant->id,
            'device_uuid' => 'uuid-1',
            'status' => RegisteredDevice::STATUS_ACTIVE,
        ]);
    }

    public function test_activation_is_idempotent_per_fingerprint(): void
    {
        $token = Str::random(40);
        $first = $this->service()->activate($this->tenant, $token, 'fp-same', 'uuid-2', null, $this->owner);
        $second = $this->service()->activate($this->tenant, $token, 'fp-same', 'uuid-2', null, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RegisteredDevice::query()->forTenant($this->tenant->id)->where('device_uuid', 'uuid-2')->count());
    }

    public function test_raw_token_is_never_stored_or_returned(): void
    {
        $token = Str::random(40);
        $activation = $this->service()->activate($this->tenant, $token, 'fp-hash', 'uuid-3', null, $this->owner);

        // The stored hash is the sha256 of the token, never the raw token.
        $this->assertNotSame($token, $activation->activation_token_hash);
        $this->assertSame(hash('sha256', $token), $activation->activation_token_hash);

        // The safe array never exposes the token hash or fingerprint.
        $safe = $activation->toSafeArray();
        $this->assertArrayNotHasKey('activation_token_hash', $safe);
        $this->assertArrayNotHasKey('device_fingerprint_hash', $safe);
    }

    public function test_expired_token_is_denied(): void
    {
        $token = Str::random(40);
        TenantDeviceActivation::query()->create([
            'tenant_id' => $this->tenant->id,
            'activation_status' => TenantDeviceActivation::STATUS_PENDING,
            'activation_token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->expectException(AndroidRuntimeException::class);
        $this->service()->activate($this->tenant, $token, 'fp-x', 'uuid-x', null, $this->owner);
    }

    public function test_register_mismatch_is_denied(): void
    {
        $token = Str::random(40);
        TenantDeviceActivation::query()->create([
            'tenant_id' => $this->tenant->id,
            'activation_status' => TenantDeviceActivation::STATUS_PENDING,
            'activation_token_hash' => hash('sha256', $token),
            'device_fingerprint_hash' => hash('sha256', 'the-bound-device'),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        try {
            $this->service()->activate($this->tenant, $token, 'a-different-device', 'uuid-y', null, $this->owner);
            $this->fail('Expected mismatch to be denied.');
        } catch (AndroidRuntimeException $e) {
            $this->assertSame('REGISTER_MISMATCH', $e->reasonCode);
        }
    }

    public function test_manual_suspension_blocks_activation(): void
    {
        TenantManualSuspension::query()->create([
            'tenant_id' => $this->tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'hold',
            'effective_at' => Carbon::now(),
        ]);

        try {
            $this->service()->activate($this->tenant, Str::random(40), 'fp-susp', 'uuid-s', null, $this->owner);
            $this->fail('Expected suspension to block activation.');
        } catch (AndroidRuntimeException $e) {
            $this->assertSame('MANUALLY_SUSPENDED', $e->reasonCode);
        }
    }

    public function test_no_unlimited_fallback_when_plan_assignment_missing(): void
    {
        // ADR-R006 — a tenant with no plan assignment must NOT become "unlimited";
        // the Sprint 26 resolver falls back to a real, RESTRICTED default plan whose
        // device limit still applies. Governance locks fail-closed posture.
        $this->assertTrue((bool) config('entitlement_governance.fail_closed_on_unknown_plan'));
        $this->assertFalse((bool) config('entitlement_governance.unknown_plan_grants_unlimited_allowed'));

        $this->tenant->planAssignments()->delete();

        // Exceed the restricted default plan device cap → activation is denied,
        // proving the fallback plan is not unlimited.
        for ($i = 0; $i < 60; $i++) {
            RegisteredDevice::query()->create([
                'tenant_id' => $this->tenant->id,
                'device_uuid' => 'nd-'.$i,
                'device_name' => 'D'.$i,
                'platform' => RegisteredDevice::PLATFORM_ANDROID,
                'registered_at' => now(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
            ]);
        }

        $this->expectException(AndroidRuntimeException::class);
        $this->service()->activate($this->tenant, Str::random(40), 'fp-noplan', 'uuid-np', null, $this->owner);
    }

    public function test_over_device_limit_is_denied(): void
    {
        $this->assignTenantPlan($this->tenant, 'starter');

        // Register many active devices so the plan device limit is exceeded.
        for ($i = 0; $i < 60; $i++) {
            RegisteredDevice::query()->create([
                'tenant_id' => $this->tenant->id,
                'device_uuid' => 'dev-'.$i,
                'device_name' => 'D'.$i,
                'platform' => RegisteredDevice::PLATFORM_ANDROID,
                'registered_at' => now(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
            ]);
        }

        try {
            $this->service()->activate($this->tenant, Str::random(40), 'fp-over', 'uuid-over', null, $this->owner);
            $this->fail('Expected over-limit activation to be denied.');
        } catch (AndroidRuntimeException $e) {
            $this->assertTrue($e->status >= 400);
        }
    }

    public function test_revoked_activation_blocks_future_sync(): void
    {
        $activation = $this->service()->activate($this->tenant, Str::random(40), 'fp-rev', 'uuid-rev', null, $this->owner);

        app(DeviceRevocationService::class)->revoke($activation, User::factory()->platformAdmin()->create(), 'lost device');

        $activation->refresh();
        $this->assertSame(TenantDeviceActivation::STATUS_REVOKED, $activation->activation_status);
        $this->assertFalse($activation->isUsable());
        $this->assertDatabaseHas('registered_devices', [
            'id' => $activation->device_id,
            'status' => RegisteredDevice::STATUS_REVOKED,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ANDROID_DEVICE_REVOKED',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
