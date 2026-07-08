<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 23 — validates update of a SaaS billing cycle. Status transitions use the
 * dedicated open/lock/close endpoints, not this generic patch.
 */
class UpdateBillingCycleRequest extends FormRequest
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
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
            'billing_month' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
