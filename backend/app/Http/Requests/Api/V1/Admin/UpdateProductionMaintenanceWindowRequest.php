<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionMaintenanceWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates updates to a production maintenance window (admin).
 */
class UpdateProductionMaintenanceWindowRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'scheduled_start_at' => ['sometimes', 'date'],
            'scheduled_end_at' => ['sometimes', 'date'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date'],
            'risk_level' => ['sometimes', Rule::in(ProductionMaintenanceWindow::RISK_LEVELS)],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rollback_plan_reference' => ['nullable', 'string', 'max:255'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
