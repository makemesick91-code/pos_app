<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates a support device revoke (SUP-R005/R012).
 */
class SupportDeviceRevokeRequest extends FormRequest
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
