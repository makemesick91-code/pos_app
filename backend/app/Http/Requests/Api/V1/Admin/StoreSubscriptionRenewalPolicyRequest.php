<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates creation of a subscription renewal policy. Free-text is
 * sanitized in the service; no secret data is accepted.
 */
class StoreSubscriptionRenewalPolicyRequest extends FormRequest
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
            'policy_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_policies,policy_reference'],
            'code' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_policies,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(SubscriptionRenewalPolicy::STATUSES)],
            'renewal_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dunning_start_days_before_expiry' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_manual_dunning_notices' => ['nullable', 'integer', 'min:1', 'max:50'],
            'requires_manual_approval' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
