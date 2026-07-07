<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsiteRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates update of a public website risk.
 */
class UpdatePublicWebsiteRiskRequest extends FormRequest
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
            'area' => ['nullable', Rule::in(PublicWebsiteRisk::AREAS)],
            'severity' => ['nullable', Rule::in(PublicWebsiteRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(PublicWebsiteRisk::STATUSES)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
