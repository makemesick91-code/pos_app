<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 50);

        return [
            'tenant_id' => Tenant::factory(),
            'store_id' => Store::factory(),
            'product_id' => Product::factory(),
            'movement_type' => InventoryMovement::TYPE_OPENING,
            'qty' => $qty,
            'signed_qty' => $qty,
            'reference_type' => null,
            'reference_id' => null,
            'source' => InventoryMovement::SOURCE_OPENING,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
