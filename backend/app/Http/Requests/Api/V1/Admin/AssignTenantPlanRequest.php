<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 26 — validates a platform-admin tenant plan assignment. The plan key is
 * constrained to the catalogue plan keys; a reason is recommended and source is
 * constrained to the governance allowlist. Authorization is enforced by
 * platform.admin (TPE-R006).
 */
class AssignTenantPlanRequest extends FormRequest
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
            'plan_key' => ['required', 'string', Rule::in((array) config('tenant_plan.plan_keys', []))],
            'source' => ['nullable', 'string', Rule::in((array) config('tenant_plan.assignment_sources', []))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
