<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'sale_id' => Sale::factory(),
            'method' => Payment::METHOD_CASH,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'status' => Payment::STATUS_PAID,
            'provider' => Payment::PROVIDER_MANUAL,
            'provider_reference' => null,
            'paid_at' => now(),
            'expired_at' => null,
            'raw_response' => null,
        ];
    }
}
