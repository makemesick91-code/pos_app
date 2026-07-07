<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan-'.strtolower(Str::random(6)),
            'name' => fake()->words(2, true),
            'description' => null,
            'price_monthly' => fake()->randomElement([0, 49000, 99000, 199000]),
            'max_stores' => 1,
            'max_devices' => 1,
            'max_products' => null,
            'features' => null,
            'is_active' => true,
        ];
    }

    public function lite(): static
    {
        return $this->state(fn () => [
            'code' => SubscriptionPlan::CODE_LITE,
            'name' => 'Lite',
            'max_stores' => 1,
            'max_devices' => 1,
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn () => [
            'code' => SubscriptionPlan::CODE_STARTER,
            'name' => 'Starter',
            'max_stores' => 1,
            'max_devices' => 3,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'code' => SubscriptionPlan::CODE_PRO,
            'name' => 'Pro',
            'max_stores' => 3,
            'max_devices' => 10,
        ]);
    }
}
