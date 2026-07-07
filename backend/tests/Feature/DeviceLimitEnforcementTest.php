<?php

namespace Tests\Feature;

use App\Models\RegisteredDevice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 10 — the plan's max_devices is backend authoritative. Active devices
 * count against it, revoked devices do not, over-limit registration is 403, and
 * a duplicate active device never consumes a second slot.
 */
class DeviceLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        // Start from a clean slate with a dedicated 2-device plan.
        $this->resetSubscriptionState($this->tenant);
        $plan = SubscriptionPlan::factory()->create([
            'code' => 'limit-plan',
            'max_devices' => 2,
            'max_stores' => 1,
        ]);
        $this->attachActiveSubscription($this->tenant, $plan);
    }

    private function register(string $uuid): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/devices/register', ['device_uuid' => $uuid, 'platform' => 'ANDROID']);
    }

    public function test_registration_over_limit_is_rejected(): void
    {
        $this->register('dev-1')->assertCreated();
        $this->register('dev-2')->assertCreated();

        $this->register('dev-3')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_LIMIT_REACHED')
            ->assertJsonPath('max_devices', 2)
            ->assertJsonPath('active_count', 2);
    }

    public function test_revoked_devices_do_not_count_against_the_limit(): void
    {
        $this->register('dev-1')->assertCreated();
        $device2Id = $this->register('dev-2')->assertCreated()->json('data.id');

        // Revoke one — a slot frees up.
        RegisteredDevice::query()->whereKey($device2Id)->update([
            'status' => RegisteredDevice::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $this->register('dev-3')->assertCreated();

        $this->assertSame(
            2,
            $this->tenant->registeredDevices()->where('status', RegisteredDevice::STATUS_ACTIVE)->count(),
        );
    }

    public function test_duplicate_active_device_does_not_consume_another_slot(): void
    {
        $this->register('dev-1')->assertCreated();
        // Re-registering the same UUID replays; still one slot used, room for one more.
        $this->register('dev-1')->assertOk()->assertJsonPath('meta.existing_device', true);

        $this->register('dev-2')->assertCreated();
        $this->register('dev-3')->assertStatus(403)->assertJsonPath('code', 'DEVICE_LIMIT_REACHED');
    }
}
