<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsiteRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates creation of a public website risk. Free-text is sanitized
 * in the service; no secret or private customer data is accepted.
 */
class StorePublicWebsiteRiskRequest extends FormRequest
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
            'risk_reference' => ['nullable', 'string', 'max:255', 'unique:public_website_risks,risk_reference'],
            'area' => ['required', Rule::in(PublicWebsiteRisk::AREAS)],
            'severity' => ['required', Rule::in(PublicWebsiteRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(PublicWebsiteRisk::STATUSES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mitigation' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
