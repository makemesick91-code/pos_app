<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionOperationRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates filtering of production operation runs (platform admin).
 */
class IndexProductionOperationRunRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(ProductionOperationRun::STATUSES)],
            'decision' => ['sometimes', Rule::in(ProductionOperationRun::DECISIONS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
