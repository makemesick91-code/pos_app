<?php

namespace Database\Factories;

use App\Models\RegisteredDevice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Well-known device UUID auto-registered for every factory tenant so the
     * Sprint 10 device.registered middleware passes for the existing Sprint 2–9
     * feature suites (which send this UUID via the base TestCase). Sprint 10
     * subscription/device tests reset this state explicitly with
     * resetSubscriptionState().
     */
    public const AUTO_DEVICE_UUID = 'test-device-uuid';

    /**
     * Every freshly created tenant is provisioned with an ACTIVE Starter
     * subscription and one ACTIVE registered device (Sprint 10). This keeps the
     * subscription.active + device.registered gate satisfied by default so the
     * Sprint 2–9 suites remain green without per-test wiring.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            $plan = SubscriptionPlan::query()->firstOrCreate(
                ['code' => SubscriptionPlan::CODE_STARTER],
                [
                    'name' => 'Starter',
                    'price_monthly' => 99000,
                    'max_stores' => 1,
                    'max_devices' => 3,
                    'is_active' => true,
                ],
            );

            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addMonth(),
            ]);

            RegisteredDevice::query()->create([
                'tenant_id' => $tenant->id,
                'device_uuid' => self::AUTO_DEVICE_UUID,
                'device_name' => 'Auto Test Device',
                'platform' => RegisteredDevice::PLATFORM_ANDROID,
                'app_version' => '1.0.0',
                'last_seen_at' => now(),
                'registered_at' => now(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'name' => fake()->company(),
            'business_type' => fake()->randomElement(['fnb', 'retail', 'service']),
            'owner_name' => fake()->name(),
            'owner_phone' => fake()->numerify('08##########'),
            'status' => Tenant::STATUS_ACTIVE,
            'subscription_plan' => 'STARTER',
            'subscription_status' => 'ACTIVE',
            'subscription_started_at' => now(),
            'subscription_ends_at' => now()->addMonth(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_SUSPENDED,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_INACTIVE,
        ]);
    }
}
