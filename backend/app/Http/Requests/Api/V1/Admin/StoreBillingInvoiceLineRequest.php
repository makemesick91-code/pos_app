<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingInvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates an invoice line. line_total is calculated server-side; no
 * external billing provider is called.
 */
class StoreBillingInvoiceLineRequest extends FormRequest
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
            'line_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_invoice_lines,line_reference'],
            'item_type' => ['required', Rule::in(SaasBillingInvoiceLine::ITEM_TYPES)],
            'description' => ['required', 'string', 'max:500'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
