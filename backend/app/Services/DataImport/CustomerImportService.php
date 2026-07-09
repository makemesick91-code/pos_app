<?php

namespace App\Services\DataImport;

use App\Models\TenantCustomer;

class CustomerImportService
{
    public function apply(int $tenantId, ?int $branchId, array $row): array
    {
        $customer = TenantCustomer::query()->where('tenant_id', $tenantId)->where('code', $row['code'])->first();
        $data = [
            'tenant_id' => $tenantId,
            'code' => $row['code'],
            'name' => $row['name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($customer === null) {
            return ['action' => 'created', 'subject' => TenantCustomer::query()->create($data)];
        }

        $customer->fill($data)->save();

        return ['action' => 'updated', 'subject' => $customer];
    }
}
