<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a product. tenant_id is never accepted from the client. store_id /
 * category_id must belong to the tenant context. sku/barcode uniqueness is
 * scoped per tenant and ignores the product being updated.
 */
class UpdateProductRequest extends FormRequest
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

        $routeProduct = $this->route('product');
        $productId = $routeProduct instanceof Product ? $routeProduct->getKey() : $routeProduct;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->where('tenant_id', $tenantId)->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->where('tenant_id', $tenantId)->ignore($productId),
            ],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'unit' => ['nullable', 'string', 'max:32'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_stock_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
