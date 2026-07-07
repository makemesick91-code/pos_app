<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'device_id' => null,
            'cashier_id' => User::factory(),
            'invoice_number' => 'POS-XX-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'sale_date' => now(),
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'paid_total' => 0,
            'change_total' => 0,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
            'sync_status' => Sale::SYNC_STATUS_SYNCED,
            'source' => Sale::SOURCE_ANDROID_ONLINE,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => Sale::PAYMENT_STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
