<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionHandoverPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 18 — filters for listing production handover packages (platform-admin
 * only; the platform.admin middleware authorizes the route).
 */
class IndexProductionHandoverRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(ProductionHandoverPackage::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
