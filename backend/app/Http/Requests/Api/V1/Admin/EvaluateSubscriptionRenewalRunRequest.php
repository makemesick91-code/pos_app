<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 24 — validates evaluating a subscription renewal run into candidates.
 * Evaluation is read-only awareness; it never renews/charges/suspends.
 */
class EvaluateSubscriptionRenewalRunRequest extends FormRequest
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
            'run_date' => ['nullable', 'date'],
        ];
    }
}
