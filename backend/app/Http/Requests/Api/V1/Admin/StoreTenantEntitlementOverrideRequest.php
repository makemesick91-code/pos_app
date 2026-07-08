<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 26 — validates a platform-admin tenant entitlement override. The
 * entitlement key is constrained to the registry, enabled is mandatory, and a
 * reason is mandatory (TPE-R006, TPE-R007). Authorization is enforced by
 * platform.admin.
 */
class StoreTenantEntitlementOverrideRequest extends FormRequest
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
            'entitlement_key' => ['required', 'string', Rule::in(array_keys((array) config('tenant_plan.entitlements', [])))],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'reason_category' => ['nullable', 'string', Rule::in((array) config('tenant_plan.override_reason_categories', []))],
            'effective_until' => ['nullable', 'date', 'after:now'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
