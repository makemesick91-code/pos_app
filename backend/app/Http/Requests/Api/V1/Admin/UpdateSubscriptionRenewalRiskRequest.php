<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates updating a subscription renewal risk.
 */
class UpdateSubscriptionRenewalRiskRequest extends FormRequest
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
            'candidate_id' => ['nullable', 'integer', 'exists:subscription_renewal_candidates,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_subscription_id' => ['nullable', 'integer', 'exists:tenant_subscriptions,id'],
            'area' => ['sometimes', Rule::in(SubscriptionRenewalRisk::AREAS)],
            'severity' => ['sometimes', Rule::in(SubscriptionRenewalRisk::SEVERITIES)],
            'status' => ['sometimes', Rule::in(SubscriptionRenewalRisk::STATUSES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
