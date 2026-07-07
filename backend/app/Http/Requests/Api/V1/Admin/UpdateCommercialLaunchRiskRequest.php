<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\CommercialLaunchRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates updating a commercial launch risk.
 */
class UpdateCommercialLaunchRiskRequest extends FormRequest
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
            'area' => ['sometimes', Rule::in(CommercialLaunchRisk::AREAS)],
            'severity' => ['sometimes', Rule::in(CommercialLaunchRisk::SEVERITIES)],
            'status' => ['sometimes', Rule::in(CommercialLaunchRisk::STATUSES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'commercial_launch_run_id' => ['nullable', 'integer', 'exists:commercial_launch_runs,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
