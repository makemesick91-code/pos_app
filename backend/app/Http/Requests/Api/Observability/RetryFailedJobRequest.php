<?php

namespace App\Http\Requests\Api\Observability;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 36 — validates a governed failed-job retry (OBS-R010/R028).
 * A reason code is always required, even when retry is disabled by default.
 */
class RetryFailedJobRequest extends FormRequest
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
            'reason_code' => ['required', 'string', Rule::in((array) config('observability_governance.reason_codes', []))],
        ];
    }
}
