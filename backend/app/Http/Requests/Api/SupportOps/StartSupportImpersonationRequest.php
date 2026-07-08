<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates a support impersonation start attempt (SUP-R005/R018).
 * Impersonation is disabled by default; the service fails closed with a governed
 * disabled response.
 */
class StartSupportImpersonationRequest extends FormRequest
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
