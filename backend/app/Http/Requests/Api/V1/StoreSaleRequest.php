<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a sale (with optional inline CASH payment) for the authenticated
 * tenant. The client supplies only what it is allowed to influence: the line
 * items, an optional store selection, the payment method/amount, and notes.
 *
 * Everything authoritative — tenant_id, cashier_id, invoice_number, and all
 * money totals — is set by the backend and explicitly prohibited from the
 * client so a forged total can never become the source of truth.
 */
class StoreSaleRequest extends FormRequest
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
            // Client may never assign these — the backend owns them.
            'tenant_id' => ['prohibited'],
            'cashier_id' => ['prohibited'],
            'invoice_number' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount_total' => ['prohibited'],
            'grand_total' => ['prohibited'],
            'paid_total' => ['prohibited'],
            'change_total' => ['prohibited'],

            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payment' => ['required', 'array'],
            'payment.method' => ['required', 'string', Rule::in(['CASH'])],
            'payment.paid_amount' => ['required', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
