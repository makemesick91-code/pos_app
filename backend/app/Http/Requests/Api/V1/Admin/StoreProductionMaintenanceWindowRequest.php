<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionMaintenanceWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates creation of a production maintenance window (admin). A
 * maintenance window record never performs a deployment; HIGH/CRITICAL windows
 * without a rollback plan reference are surfaced as WATCH/NO-GO by the service.
 */
class StoreProductionMaintenanceWindowRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['required', 'date', 'after_or_equal:scheduled_start_at'],
            'risk_level' => ['required', Rule::in(ProductionMaintenanceWindow::RISK_LEVELS)],
            'status' => ['sometimes', Rule::in(ProductionMaintenanceWindow::STATUSES)],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rollback_plan_reference' => ['nullable', 'string', 'max:255'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
