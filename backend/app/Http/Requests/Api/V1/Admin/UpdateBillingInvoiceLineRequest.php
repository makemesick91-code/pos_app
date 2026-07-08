<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingInvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates an invoice line update. line_total is recalculated
 * server-side.
 */
class UpdateBillingInvoiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_type' => ['sometimes', Rule::in(SaasBillingInvoiceLine::ITEM_TYPES)],
            'description' => ['sometimes', 'string', 'max:500'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit_amount' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
