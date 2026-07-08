<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 23 — validates a lightweight metadata edit of a DRAFT invoice. Money
 * totals are never editable here.
 */
class UpdateBillingInvoiceRequest extends FormRequest
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_subscription_id' => ['nullable', 'integer', 'exists:tenant_subscriptions,id'],
            'billing_cycle_id' => ['nullable', 'integer', 'exists:saas_billing_cycles,id'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
