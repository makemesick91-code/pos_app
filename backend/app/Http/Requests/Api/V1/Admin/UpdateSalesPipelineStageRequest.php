<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates updating a sales pipeline stage.
 */
class UpdateSalesPipelineStageRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(SalesPipelineStage::STATUSES)],
            'is_default' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
