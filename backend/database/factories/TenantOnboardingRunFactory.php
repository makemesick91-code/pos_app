<?php

namespace Database\Factories;

use App\Models\TenantOnboardingRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantOnboardingRun>
 */
class TenantOnboardingRunFactory extends Factory
{
    protected $model = TenantOnboardingRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_reference' => 'onboard-'.Str::lower(Str::random(10)),
            'requested_by' => User::factory()->platformAdmin(),
            'tenant_id' => null,
            'default_store_id' => null,
            'owner_user_id' => null,
            'subscription_plan_id' => null,
            'tenant_subscription_id' => null,
            'status' => TenantOnboardingRun::STATUS_PENDING,
            'tenant_name' => fake()->company(),
            'store_name' => 'Toko Pusat',
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'demo_data_enabled' => false,
            'checklist' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantOnboardingRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantOnboardingRun::STATUS_FAILED,
            'error_message' => 'Onboarding failed.',
            'completed_at' => now(),
        ]);
    }
}
