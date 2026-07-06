<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update a store price override. Only the price and active flag may change;
 * the store/product binding is fixed at creation to preserve the unique triple.
 */
class UpdateProductStorePriceRequest extends FormRequest
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
        return [
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
