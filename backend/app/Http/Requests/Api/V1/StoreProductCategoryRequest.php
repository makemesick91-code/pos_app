<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a product category for the authenticated tenant. tenant_id is never
 * accepted from the client; any store_id must belong to the tenant context.
 */
class StoreProductCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
