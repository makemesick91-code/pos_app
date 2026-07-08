<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates a support incident creation. platform.admin is enforced
 * by the route middleware; a reason code is mandatory (SUP-R005).
 */
class StoreSupportIncidentRequest extends FormRequest
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
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'reason_code' => ['required', 'string', Rule::in((array) config('support_operations_governance.reason_codes', []))],
            'category' => ['required', 'string', Rule::in((array) config('support_operations_governance.incidents.categories', []))],
            'severity' => ['required', 'string', Rule::in((array) config('support_operations_governance.incidents.severities', []))],
            'title' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'related_subject_type' => ['nullable', 'string', 'max:255'],
            'related_subject_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
