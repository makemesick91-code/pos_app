<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PilotDefect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 17 — validates creation of a pilot defect. tenant_id/store_id are
 * optional (some defects are global); when a store is given it must belong to
 * the tenant (enforced by PilotDefectService). No secret is ever accepted as a
 * dedicated field; the service also sanitises free-text/metadata.
 */
class StorePilotDefectRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'area' => ['required', Rule::in(PilotDefect::AREAS)],
            'severity' => ['required', Rule::in(PilotDefect::SEVERITIES)],
            'status' => ['sometimes', Rule::in(PilotDefect::STATUSES)],
            'blocking' => ['sometimes', 'boolean'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'steps_to_reproduce' => ['nullable', 'string'],
            'expected_result' => ['nullable', 'string'],
            'actual_result' => ['nullable', 'string'],
            'environment' => ['nullable', 'array'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
