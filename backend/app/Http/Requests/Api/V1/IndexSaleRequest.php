<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for listing the authenticated tenant's sales. All filters are
 * optional; store_id, when present, must belong to the tenant context.
 */
class IndexSaleRequest extends FormRequest
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', 'max:20'],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
