<?php

namespace App\Services\DataImport;

use App\Models\Product;
use App\Models\Store;

class ImportValidationService
{
    public function __construct(private readonly ImportRedactor $redactor) {}

    /**
     * @return array{valid: bool, normalized: array<string, mixed>, errors: array<int, array{code: string, message: string}>}
     */
    public function validate(int $tenantId, ?int $branchId, string $type, array $row): array
    {
        $normalized = $this->normalize($row);
        $errors = [];

        foreach ((array) config('import_governance.required_headers.'.$type, []) as $header) {
            if (($normalized[$header] ?? '') === '') {
                $errors[] = ['code' => 'REQUIRED', 'message' => "Missing required field {$header}."];
            }
        }

        if (in_array($type, ['supplier', 'customer', 'payment_method', 'default_settings'], true) && $this->redactor->containsForbiddenSecretKey($normalized)) {
            $errors[] = ['code' => 'SECRET_REJECTED', 'message' => 'Rows containing credential or secret fields are not allowed.'];
        }

        if (in_array($type, ['product', 'price'], true) && isset($normalized['selling_price']) && ! $this->validMoney($normalized['selling_price'])) {
            $errors[] = ['code' => 'INVALID_PRICE', 'message' => 'Selling price must be zero or greater.'];
        }

        if ($type === 'initial_stock' && (! $this->validPositive($normalized['qty'] ?? null))) {
            $errors[] = ['code' => 'INVALID_QTY', 'message' => 'Quantity must be greater than zero.'];
        }

        if (in_array($type, ['initial_stock', 'price'], true)) {
            $store = $this->storeFor($tenantId, $branchId, $normalized['store_code'] ?? null);
            if ($store === null) {
                $errors[] = ['code' => 'STORE_NOT_FOUND', 'message' => 'Store is required and must belong to the tenant.'];
            } else {
                $normalized['store_id'] = (int) $store->id;
            }
        }

        if (in_array($type, ['initial_stock', 'price'], true)) {
            $product = Product::query()->forTenant($tenantId)->where('sku', (string) ($normalized['sku'] ?? ''))->first();
            if ($product === null) {
                $errors[] = ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Product SKU must exist for this tenant.'];
            } else {
                $normalized['product_id'] = (int) $product->id;
            }
        }

        return ['valid' => $errors === [], 'normalized' => $normalized, 'errors' => $errors];
    }

    public function validateHeaders(string $type, array $firstRow): array
    {
        $missing = array_values(array_diff((array) config('import_governance.required_headers.'.$type, []), array_keys($firstRow)));

        return array_map(fn (string $header) => ['code' => 'MISSING_HEADER', 'message' => "Missing required header {$header}."], $missing);
    }

    private function normalize(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        foreach (['is_active', 'is_default', 'is_stock_tracked'] as $key) {
            if (array_key_exists($key, $normalized)) {
                $normalized[$key] = filter_var($normalized[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return $normalized;
    }

    private function validMoney(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    private function validPositive(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }

    private function storeFor(int $tenantId, ?int $branchId, ?string $storeCode): ?Store
    {
        $query = Store::query()->where('tenant_id', $tenantId)->where('is_active', true);
        if ($storeCode !== null && $storeCode !== '') {
            return $query->where('code', $storeCode)->first();
        }
        if ($branchId !== null) {
            return $query->whereKey($branchId)->first();
        }

        return null;
    }
}
