<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingCollectionRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates creation of a billing collection risk. Free-text is
 * sanitized in the service; no secret data is accepted.
 */
class StoreBillingCollectionRiskRequest extends FormRequest
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
            'risk_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_collection_risks,risk_reference'],
            'billing_account_id' => ['nullable', 'integer', 'exists:saas_billing_accounts,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'area' => ['required', Rule::in(SaasBillingCollectionRisk::AREAS)],
            'severity' => ['required', Rule::in(SaasBillingCollectionRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(SaasBillingCollectionRisk::STATUSES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
