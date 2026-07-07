<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionMaintenanceWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — transition a production maintenance window's status (admin).
 */
class TransitionProductionMaintenanceWindowStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(ProductionMaintenanceWindow::STATUSES)],
        ];
    }
}
