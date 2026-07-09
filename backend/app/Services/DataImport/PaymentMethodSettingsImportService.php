<?php

namespace App\Services\DataImport;

use App\Models\TenantDefaultSetting;
use App\Models\TenantPaymentMethod;
use Illuminate\Validation\ValidationException;

class PaymentMethodSettingsImportService
{
    public function applyPaymentMethod(int $tenantId, ?int $branchId, array $row): array
    {
        $this->rejectGatewayCredentials($row);

        $method = TenantPaymentMethod::query()->where('tenant_id', $tenantId)->where('code', $row['code'])->first();
        $data = [
            'tenant_id' => $tenantId,
            'code' => $row['code'],
            'name' => $row['name'],
            'method_type' => $row['method_type'] ?? 'cash',
            'is_default' => (bool) ($row['is_default'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];

        if ($method === null) {
            return ['action' => 'created', 'subject' => TenantPaymentMethod::query()->create($data)];
        }

        $method->fill($data)->save();

        return ['action' => 'updated', 'subject' => $method];
    }

    public function applyDefaultSetting(int $tenantId, ?int $branchId, array $row): array
    {
        $this->rejectGatewayCredentials($row);

        $setting = TenantDefaultSetting::query()->where('tenant_id', $tenantId)->where('setting_key', $row['setting_key'])->first();
        $data = [
            'tenant_id' => $tenantId,
            'setting_key' => $row['setting_key'],
            'setting_value' => $row['setting_value'] ?? null,
        ];

        if ($setting === null) {
            return ['action' => 'created', 'subject' => TenantDefaultSetting::query()->create($data)];
        }

        $setting->fill($data)->save();

        return ['action' => 'updated', 'subject' => $setting];
    }

    private function rejectGatewayCredentials(array $row): void
    {
        foreach (array_keys($row) as $key) {
            $lower = strtolower((string) $key);
            foreach (['secret', 'token', 'credential', 'server_key', 'client_key', 'private_key', 'api_key', 'password'] as $blocked) {
                if (str_contains($lower, $blocked)) {
                    throw ValidationException::withMessages(['row' => 'Gateway credentials and secrets cannot be imported.']);
                }
            }
        }
    }
}
