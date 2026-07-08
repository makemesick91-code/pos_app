<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates update of a SaaS billing account. A status change never
 * suspends tenant access.
 */
class UpdateBillingAccountRequest extends FormRequest
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
            'billing_name' => ['sometimes', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:64'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'tax_identifier' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(SaasBillingAccount::STATUSES)],
            'billing_currency' => ['sometimes', 'string', 'max:8'],
            'payment_terms_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'collection_owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
