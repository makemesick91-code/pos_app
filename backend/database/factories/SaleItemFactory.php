<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 1000, 100000);

        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'product_sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'product_barcode' => null,
            'unit' => 'pcs',
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'subtotal' => $qty * $unitPrice,
        ];
    }
}
