<?php

namespace App\Services\DataImport;

use App\Models\ProductStorePrice;

class PriceImportService
{
    public function apply(int $tenantId, ?int $branchId, array $row): array
    {
        $price = ProductStorePrice::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $row['store_id'])
            ->where('product_id', $row['product_id'])
            ->first();

        $data = [
            'tenant_id' => $tenantId,
            'store_id' => $row['store_id'],
            'product_id' => $row['product_id'],
            'selling_price' => $row['selling_price'],
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($price === null) {
            return ['action' => 'created', 'subject' => ProductStorePrice::query()->create($data)];
        }

        $price->fill($data)->save();

        return ['action' => 'updated', 'subject' => $price];
    }
}
