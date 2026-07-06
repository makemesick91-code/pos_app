<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a store price override. tenant_id is never accepted from the client.
 * store_id and product_id must belong to the tenant context, and the
 * (tenant, store, product) triple must be unique.
 */
class StoreProductStorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->hasTenant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $tenantId = app(TenantContext::class)->tenantId();

        $validator->after(function ($validator) use ($tenantId) {
            $storeId = $this->input('store_id');
            $productId = $this->input('product_id');

            if ($storeId === null || $productId === null) {
                return;
            }

            $exists = \App\Models\ProductStorePrice::query()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'product_id',
                    'A price override already exists for this store and product.'
                );
            }
        });
    }
}
