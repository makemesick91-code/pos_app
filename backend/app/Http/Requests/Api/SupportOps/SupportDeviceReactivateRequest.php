<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates a support device reactivate attempt (SUP-R005/R013). The
 * service fails closed with a governed not-supported response by default.
 */
class SupportDeviceReactivateRequest extends FormRequest
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
        ];
    }
}
