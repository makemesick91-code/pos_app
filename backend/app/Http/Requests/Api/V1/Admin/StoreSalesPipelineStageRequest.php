<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates creation of a sales pipeline stage. Governance metadata
 * only; no secret data is accepted.
 */
class StoreSalesPipelineStageRequest extends FormRequest
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
            'stage_code' => ['required', 'string', 'max:255', 'unique:sales_pipeline_stages,stage_code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(SalesPipelineStage::STATUSES)],
            'is_default' => ['nullable', 'boolean'],
            'is_terminal' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
