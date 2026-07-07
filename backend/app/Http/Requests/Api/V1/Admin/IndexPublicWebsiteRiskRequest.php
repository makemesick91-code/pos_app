<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsiteRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates public website risk listing filters.
 */
class IndexPublicWebsiteRiskRequest extends FormRequest
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
            'severity' => ['nullable', Rule::in(PublicWebsiteRisk::SEVERITIES)],
            'status' => ['nullable', Rule::in(PublicWebsiteRisk::STATUSES)],
            'area' => ['nullable', Rule::in(PublicWebsiteRisk::AREAS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
