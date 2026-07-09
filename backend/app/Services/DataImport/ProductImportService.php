<?php

namespace App\Services\DataImport;

use App\Models\Product;
use App\Models\ProductCategory;

class ProductImportService
{
    public function apply(int $tenantId, ?int $branchId, array $row): array
    {
        $categoryId = null;
        if (! empty($row['category'])) {
            $categoryId = ProductCategory::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($branchId) {
                    $query->whereNull('store_id');
                    if ($branchId !== null) {
                        $query->orWhere('store_id', $branchId);
                    }
                })
                ->where('name', $row['category'])
                ->value('id');
        }

        $product = Product::query()->where('tenant_id', $tenantId)->where('sku', $row['sku'])->first();
        $data = [
            'tenant_id' => $tenantId,
            'store_id' => $branchId,
            'category_id' => $categoryId,
            'sku' => $row['sku'],
            'barcode' => $row['barcode'] ?? null,
            'name' => $row['name'],
            'unit' => $row['unit'] ?? 'pcs',
            'cost_price' => $row['cost_price'] ?? null,
            'selling_price' => $row['selling_price'],
            'is_stock_tracked' => (bool) ($row['is_stock_tracked'] ?? true),
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($product === null) {
            return ['action' => 'created', 'subject' => Product::query()->create($data)];
        }

        $product->fill($data)->save();

        return ['action' => 'updated', 'subject' => $product];
    }
}
