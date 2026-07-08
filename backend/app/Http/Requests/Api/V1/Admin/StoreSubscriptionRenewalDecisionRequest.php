<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates recording a manual subscription renewal decision.
 * Recording a decision never mutates a TenantSubscription.
 */
class StoreSubscriptionRenewalDecisionRequest extends FormRequest
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
            'decision_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_decisions,decision_reference'],
            'decision' => ['required', Rule::in(SubscriptionRenewalDecision::DECISIONS)],
            'decided_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'effective_start_date' => ['nullable', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
            'approved_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'manual_billing_invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
