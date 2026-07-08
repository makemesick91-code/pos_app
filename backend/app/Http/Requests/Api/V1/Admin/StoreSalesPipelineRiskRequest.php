<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates creation of a sales pipeline risk. Free-text is sanitized
 * in the service; no secret or private customer data is accepted.
 */
class StoreSalesPipelineRiskRequest extends FormRequest
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
            'risk_reference' => ['nullable', 'string', 'max:255', 'unique:sales_pipeline_risks,risk_reference'],
            'sales_lead_id' => ['nullable', 'integer', 'exists:sales_leads,id'],
            'area' => ['required', Rule::in(SalesPipelineRisk::AREAS)],
            'severity' => ['required', Rule::in(SalesPipelineRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(SalesPipelineRisk::STATUSES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
