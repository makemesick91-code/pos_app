<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 25 — validates a platform-admin manual tenant suspension. Reason is
 * mandatory (TLS-R006); reason category is constrained to the governance
 * allowlist. Authorization is enforced by the platform.admin middleware.
 */
class SuspendTenantRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'reason_category' => ['nullable', 'string', Rule::in((array) config('tenant_lifecycle.suspension_reason_categories', []))],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
