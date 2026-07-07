<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PilotDefect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 17 — validates an update to a pilot defect. All fields are optional;
 * the service records lifecycle events for severity/status changes.
 */
class UpdatePilotDefectRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'area' => ['sometimes', Rule::in(PilotDefect::AREAS)],
            'severity' => ['sometimes', Rule::in(PilotDefect::SEVERITIES)],
            'status' => ['sometimes', Rule::in(PilotDefect::STATUSES)],
            'blocking' => ['sometimes', 'boolean'],
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'steps_to_reproduce' => ['sometimes', 'nullable', 'string'],
            'expected_result' => ['sometimes', 'nullable', 'string'],
            'actual_result' => ['sometimes', 'nullable', 'string'],
            'environment' => ['sometimes', 'nullable', 'array'],
            'evidence_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
