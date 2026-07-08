<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates updating a subscription renewal candidate. No update path
 * mutates a TenantSubscription.
 */
class UpdateSubscriptionRenewalCandidateRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(SubscriptionRenewalCandidate::STATUSES)],
            'renewal_stage' => ['sometimes', Rule::in(SubscriptionRenewalCandidate::STAGES)],
            'priority' => ['sometimes', Rule::in(SubscriptionRenewalCandidate::PRIORITIES)],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'billing_invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'billing_account_id' => ['nullable', 'integer', 'exists:saas_billing_accounts,id'],
            'last_payment_evidence_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
