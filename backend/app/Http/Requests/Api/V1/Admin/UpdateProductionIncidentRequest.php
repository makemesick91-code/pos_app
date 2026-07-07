<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates updates to a production incident (platform admin).
 */
class UpdateProductionIncidentRequest extends FormRequest
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
            'area' => ['sometimes', Rule::in(ProductionIncident::AREAS)],
            'severity' => ['sometimes', Rule::in(ProductionIncident::SEVERITIES)],
            'status' => ['sometimes', Rule::in(ProductionIncident::STATUSES)],
            'impact' => ['sometimes', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'description' => ['nullable', 'string'],
            'resolution_summary' => ['nullable', 'string'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
