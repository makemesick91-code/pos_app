<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates adding a redacted note to a support incident (SUP-R023).
 */
class StoreSupportIncidentNoteRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:10000'],
            'note_type' => ['nullable', 'string', Rule::in((array) config('support_operations_governance.incidents.note_types', []))],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
