<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingCollectionRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates update of a billing collection risk.
 */
class UpdateBillingCollectionRiskRequest extends FormRequest
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
            'billing_account_id' => ['nullable', 'integer', 'exists:saas_billing_accounts,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'area' => ['sometimes', Rule::in(SaasBillingCollectionRisk::AREAS)],
            'severity' => ['sometimes', Rule::in(SaasBillingCollectionRisk::SEVERITIES)],
            'status' => ['sometimes', Rule::in(SaasBillingCollectionRisk::STATUSES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
