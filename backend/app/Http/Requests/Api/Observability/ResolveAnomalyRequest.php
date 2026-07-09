<?php

namespace App\Http\Requests\Api\Observability;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 36 — validates an anomaly resolve/ignore (OBS-R005/R028).
 */
class ResolveAnomalyRequest extends FormRequest
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
            'ignore' => ['sometimes', 'boolean'],
        ];
    }
}
