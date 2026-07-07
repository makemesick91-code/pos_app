<?php

namespace Database\Factories;

use App\Models\DailyClosing;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyClosing>
 */
class DailyClosingFactory extends Factory
{
    protected $model = DailyClosing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'business_date' => now()->toDateString(),
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'status' => DailyClosing::STATUS_CLOSED,
            'sales_count' => 0,
            'cancelled_sales_count' => 0,
            'cash_total' => 0,
            'qris_total' => 0,
            'gross_total' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'paid_total' => 0,
            'change_total' => 0,
            'inventory_sale_out_qty' => 0,
            'snapshot' => null,
            'notes' => null,
        ];
    }
}
