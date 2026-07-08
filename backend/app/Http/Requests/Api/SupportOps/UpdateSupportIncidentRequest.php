<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates a support incident update (status/severity/assignment).
 * A reason code is mandatory (SUP-R005/R024).
 */
class UpdateSupportIncidentRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in((array) config('support_operations_governance.incidents.statuses', []))],
            'severity' => ['nullable', 'string', Rule::in((array) config('support_operations_governance.incidents.severities', []))],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
