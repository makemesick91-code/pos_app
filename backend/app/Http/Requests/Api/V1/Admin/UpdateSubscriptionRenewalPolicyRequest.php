<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates updating a subscription renewal policy.
 */
class UpdateSubscriptionRenewalPolicyRequest extends FormRequest
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
        $policyId = $this->route('policy')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('subscription_renewal_policies', 'code')->ignore($policyId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(SubscriptionRenewalPolicy::STATUSES)],
            'renewal_window_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'dunning_start_days_before_expiry' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'max_manual_dunning_notices' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'requires_manual_approval' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
