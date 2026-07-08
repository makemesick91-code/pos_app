<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates sales lead listing filters.
 */
class IndexSalesLeadRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(SalesLead::STATUSES)],
            'priority' => ['nullable', Rule::in(SalesLead::PRIORITIES)],
            'source' => ['nullable', 'string', 'max:255'],
            'pipeline_stage_id' => ['nullable', 'integer', 'exists:sales_pipeline_stages,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
