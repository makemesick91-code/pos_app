<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionMaintenanceWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates filtering of production maintenance windows (admin).
 */
class IndexProductionMaintenanceWindowRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(ProductionMaintenanceWindow::STATUSES)],
            'risk_level' => ['sometimes', Rule::in(ProductionMaintenanceWindow::RISK_LEVELS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
