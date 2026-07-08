<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates updating a sales pipeline risk.
 */
class UpdateSalesPipelineRiskRequest extends FormRequest
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
            'sales_lead_id' => ['nullable', 'integer', 'exists:sales_leads,id'],
            'area' => ['sometimes', Rule::in(SalesPipelineRisk::AREAS)],
            'severity' => ['sometimes', Rule::in(SalesPipelineRisk::SEVERITIES)],
            'status' => ['sometimes', Rule::in(SalesPipelineRisk::STATUSES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
