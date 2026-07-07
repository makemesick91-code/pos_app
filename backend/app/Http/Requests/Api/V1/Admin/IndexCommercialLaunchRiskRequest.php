<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\CommercialLaunchRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates filters for listing commercial launch risks.
 */
class IndexCommercialLaunchRiskRequest extends FormRequest
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
            'severity' => ['nullable', Rule::in(CommercialLaunchRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(CommercialLaunchRisk::STATUSES)],
            'area' => ['nullable', Rule::in(CommercialLaunchRisk::AREAS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
