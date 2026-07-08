<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingPaymentEvidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates a manual payment evidence submission. MANUAL_QRIS_REFERENCE
 * is a label only; no payment gateway is called and no secret is accepted.
 */
class StoreBillingPaymentEvidenceRequest extends FormRequest
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
            'payment_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_payment_evidences,payment_reference'],
            'payment_method' => ['required', Rule::in(SaasBillingPaymentEvidence::METHODS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['nullable', 'date'],
            'evidence_label' => ['nullable', 'string', 'max:255'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
