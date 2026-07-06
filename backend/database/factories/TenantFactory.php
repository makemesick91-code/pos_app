<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

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
