<?php

namespace App\Services\DataImport;

use App\Models\ProductCategory;

class CategoryImportService
{
    public function apply(int $tenantId, ?int $branchId, array $row): array
    {
        $category = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $branchId)
            ->where('name', $row['name'])
            ->first();

        $data = [
            'tenant_id' => $tenantId,
            'store_id' => $branchId,
            'name' => $row['name'],
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($category === null) {
            return ['action' => 'created', 'subject' => ProductCategory::query()->create($data)];
        }

        $category->fill($data)->save();

        return ['action' => 'updated', 'subject' => $category];
    }
}
