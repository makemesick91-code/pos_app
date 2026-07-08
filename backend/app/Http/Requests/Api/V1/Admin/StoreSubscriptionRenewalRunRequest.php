<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 24 — validates creation of a subscription renewal run.
 */
class StoreSubscriptionRenewalRunRequest extends FormRequest
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
            'run_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_runs,run_reference'],
            'policy_id' => ['nullable', 'integer', 'exists:subscription_renewal_policies,id'],
            'run_date' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
