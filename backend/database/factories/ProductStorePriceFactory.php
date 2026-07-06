<?php

namespace Database\Factories;

use App\Models\ProductStorePrice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStorePrice>
 */
class ProductStorePriceFactory extends Factory
{
    protected $model = ProductStorePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => null,
            'product_id' => null,
            'selling_price' => fake()->randomFloat(2, 1000, 100000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
