<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates creation of a SaaS billing cycle.
 */
class StoreBillingCycleRequest extends FormRequest
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
            'cycle_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_cycles,cycle_reference'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['nullable', Rule::in(SaasBillingCycle::STATUSES)],
            'billing_month' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
