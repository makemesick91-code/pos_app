<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InventoryMovement;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the basic (non-report) inventory movement listing. All filters are
 * optional and scoped to the authenticated tenant. See Sprint 8 evidence.
 */
class IndexInventoryMovementRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'movement_type' => [
                'nullable',
                'string',
                Rule::in([
                    InventoryMovement::TYPE_OPENING,
                    InventoryMovement::TYPE_SALE_OUT,
                    InventoryMovement::TYPE_ADJUSTMENT_IN,
                    InventoryMovement::TYPE_ADJUSTMENT_OUT,
                    InventoryMovement::TYPE_RETURN_IN,
                ]),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
