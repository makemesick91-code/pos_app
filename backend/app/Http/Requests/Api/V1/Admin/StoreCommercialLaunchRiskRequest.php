<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\CommercialLaunchRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates creation of a commercial launch risk. No secret or private
 * customer data is accepted; free-text is sanitized in the service.
 */
class StoreCommercialLaunchRiskRequest extends FormRequest
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
            'risk_reference' => ['nullable', 'string', 'max:255', 'unique:commercial_launch_risks,risk_reference'],
            'commercial_launch_run_id' => ['nullable', 'integer', 'exists:commercial_launch_runs,id'],
            'area' => ['required', Rule::in(CommercialLaunchRisk::AREAS)],
            'severity' => ['required', Rule::in(CommercialLaunchRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(CommercialLaunchRisk::STATUSES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
