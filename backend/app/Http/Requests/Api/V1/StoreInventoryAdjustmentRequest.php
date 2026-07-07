<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InventoryMovement;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A manual inventory adjustment (OPENING / ADJUSTMENT_IN / ADJUSTMENT_OUT).
 * SALE_OUT is deliberately NOT accepted here — stock is only removed through a
 * real sale. tenant_id is taken from context, never the request; store_id and
 * product_id are validated to belong to the tenant. signed_qty is computed by
 * the backend. See Sprint 8 evidence.
 */
class StoreInventoryAdjustmentRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'movement_type' => [
                'required',
                'string',
                Rule::in(InventoryMovement::ADJUSTMENT_TYPES),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
