<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates starting a time-bound read-only support context
 * (SUP-R005/R017).
 */
class StartSupportReadOnlyContextRequest extends FormRequest
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
            'reason_code' => ['required', 'string', Rule::in((array) config('support_operations_governance.reason_codes', []))],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('support_operations_governance.read_only_context.max_ttl_minutes', 240)],
        ];
    }
}
