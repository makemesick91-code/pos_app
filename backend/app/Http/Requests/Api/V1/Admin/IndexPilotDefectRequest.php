<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PilotDefect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 17 — filters for listing pilot defects (platform-admin only; the
 * platform.admin middleware authorizes the route).
 */
class IndexPilotDefectRequest extends FormRequest
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
            'severity' => ['nullable', Rule::in(PilotDefect::SEVERITIES)],
            'status' => ['nullable', Rule::in(PilotDefect::STATUSES)],
            'area' => ['nullable', Rule::in(PilotDefect::AREAS)],
            'blocking' => ['nullable', 'boolean'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
