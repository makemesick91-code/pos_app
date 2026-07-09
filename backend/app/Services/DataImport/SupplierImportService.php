<?php

namespace App\Services\DataImport;

use App\Models\TenantSupplier;

class SupplierImportService
{
    public function apply(int $tenantId, ?int $branchId, array $row): array
    {
        $supplier = TenantSupplier::query()->where('tenant_id', $tenantId)->where('code', $row['code'])->first();
        $data = [
            'tenant_id' => $tenantId,
            'code' => $row['code'],
            'name' => $row['name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($supplier === null) {
            return ['action' => 'created', 'subject' => TenantSupplier::query()->create($data)];
        }

        $supplier->fill($data)->save();

        return ['action' => 'updated', 'subject' => $supplier];
    }
}
