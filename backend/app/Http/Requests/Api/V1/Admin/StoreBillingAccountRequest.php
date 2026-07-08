<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates creation of a SaaS billing account. Free-text is sanitized
 * in the service; no secret data is accepted. Linking a tenant never creates one.
 */
class StoreBillingAccountRequest extends FormRequest
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
            'account_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_accounts,account_reference'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:64'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'tax_identifier' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(SaasBillingAccount::STATUSES)],
            'billing_currency' => ['nullable', 'string', 'max:8'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'collection_owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
