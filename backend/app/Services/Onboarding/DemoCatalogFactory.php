<?php

namespace App\Services\Onboarding;

/**
 * Sprint 12 — the canonical demo catalog blueprint used when seeding a tenant.
 *
 * Pure data (no persistence): a deterministic set of demo categories and
 * products with a DEMO- sku prefix so the seed is repeatable and recognizable.
 * The seeder is responsible for tenant ownership and idempotency.
 */
class DemoCatalogFactory
{
    public const SKU_PREFIX = 'DEMO-';

    /**
     * @return array<int, array{name: string}>
     */
    public function categories(): array
    {
        return [
            ['name' => 'Minuman'],
            ['name' => 'Makanan'],
            ['name' => 'Jasa'],
        ];
    }

    /**
     * @return array<int, array{sku: string, name: string, category: string, unit: string, cost_price: string, selling_price: string, is_stock_tracked: bool, opening_qty: string}>
     */
    public function products(): array
    {
        return [
            [
                'sku' => self::SKU_PREFIX.'KOPI-SUSU',
                'name' => 'Kopi Susu Demo',
                'category' => 'Minuman',
                'unit' => 'cup',
                'cost_price' => '8000.00',
                'selling_price' => '18000.00',
                'is_stock_tracked' => true,
                'opening_qty' => '50',
            ],
            [
                'sku' => self::SKU_PREFIX.'TEH-MANIS',
                'name' => 'Teh Manis Demo',
                'category' => 'Minuman',
                'unit' => 'cup',
                'cost_price' => '3000.00',
                'selling_price' => '8000.00',
                'is_stock_tracked' => true,
                'opening_qty' => '50',
            ],
            [
                'sku' => self::SKU_PREFIX.'ROTI-BAKAR',
                'name' => 'Roti Bakar Demo',
                'category' => 'Makanan',
                'unit' => 'porsi',
                'cost_price' => '7000.00',
                'selling_price' => '15000.00',
                'is_stock_tracked' => true,
                'opening_qty' => '30',
            ],
            [
                'sku' => self::SKU_PREFIX.'JASA-CUCI-SEPATU',
                'name' => 'Cuci Sepatu Demo',
                'category' => 'Jasa',
                'unit' => 'pasang',
                'cost_price' => '0.00',
                'selling_price' => '35000.00',
                'is_stock_tracked' => false,
                'opening_qty' => '0',
            ],
        ];
    }
}
