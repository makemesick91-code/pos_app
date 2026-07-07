<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates filtering of production incidents (platform admin).
 */
class IndexProductionIncidentRequest extends FormRequest
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
            'severity' => ['sometimes', Rule::in(ProductionIncident::SEVERITIES)],
            'status' => ['sometimes', Rule::in(ProductionIncident::STATUSES)],
            'area' => ['sometimes', Rule::in(ProductionIncident::AREAS)],
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
