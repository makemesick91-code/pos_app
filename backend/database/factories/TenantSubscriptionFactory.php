<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    protected $model = TenantSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory()->starter(),
            'status' => TenantSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'cancelled_at' => null,
            'suspended_at' => null,
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_TRIAL,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'trial_ends_at' => now()->addWeek(),
        ]);
    }

    public function grace(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_GRACE,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(3),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(10),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_CANCELLED,
            'cancelled_at' => now()->subDay(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantSubscription::STATUS_SUSPENDED,
            'suspended_at' => now()->subDay(),
        ]);
    }
}
