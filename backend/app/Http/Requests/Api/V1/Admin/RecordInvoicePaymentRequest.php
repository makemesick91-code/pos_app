<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 30 — validate a manual payment record. Amount is a positive integer; the
 * service further enforces the outstanding/overpayment/partial policy (BIL-R009).
 */
class RecordInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin middleware authorizes.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', Rule::in((array) config('billing_governance.payment_methods', []))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', Rule::in((array) config('billing_governance.sources', []))],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
