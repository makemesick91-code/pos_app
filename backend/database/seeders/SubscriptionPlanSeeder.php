<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Seeds the backend-owned subscription plans (Sprint 10). Plans are idempotent
 * by code so re-seeding never duplicates. Prices are placeholders for the
 * foundation sprint (no real billing is charged in Sprint 10).
 */
class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => SubscriptionPlan::CODE_LITE,
                'name' => 'Lite',
                'description' => '1 store, 1 device.',
                'price_monthly' => 49000,
                'max_stores' => 1,
                'max_devices' => 1,
                'max_products' => 100,
            ],
            [
                'code' => SubscriptionPlan::CODE_STARTER,
                'name' => 'Starter',
                'description' => '1 store, up to 3 devices.',
                'price_monthly' => 99000,
                'max_stores' => 1,
                'max_devices' => 3,
                'max_products' => 500,
            ],
            [
                'code' => SubscriptionPlan::CODE_PRO,
                'name' => 'Pro',
                'description' => 'Up to 3 stores, up to 10 devices.',
                'price_monthly' => 199000,
                'max_stores' => 3,
                'max_devices' => 10,
                'max_products' => null,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                array_merge($plan, ['is_active' => true]),
            );
        }
    }
}
