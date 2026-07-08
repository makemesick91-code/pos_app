<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates transitioning a sales lead onto a pipeline stage.
 */
class TransitionSalesLeadRequest extends FormRequest
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
            'stage_code' => ['required', Rule::in(array_column((array) config('sales_pipeline.default_stage_definitions', []), 'stage_code'))],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
