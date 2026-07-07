<?php

namespace Database\Factories;

use App\Models\RegisteredDevice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegisteredDevice>
 */
class RegisteredDeviceFactory extends Factory
{
    protected $model = RegisteredDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'store_id' => null,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => fake()->randomElement(['Kasir 1', 'Kasir 2', 'Samsung A12', 'Xiaomi Redmi']),
            'platform' => RegisteredDevice::PLATFORM_ANDROID,
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
            'registered_at' => now(),
            'revoked_at' => null,
            'status' => RegisteredDevice::STATUS_ACTIVE,
            'metadata' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => RegisteredDevice::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => RegisteredDevice::STATUS_BLOCKED,
        ]);
    }
}
