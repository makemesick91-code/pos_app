<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasPackageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates updating a SaaS package catalog entry.
 */
class UpdateSaasPackageCatalogRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'target_segment' => ['sometimes', Rule::in(SaasPackageCatalog::SEGMENTS)],
            'status' => ['sometimes', Rule::in(SaasPackageCatalog::STATUSES)],
            'monthly_price' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'device_limit' => ['nullable', 'integer', 'min:0'],
            'store_limit' => ['nullable', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'onboarding_level' => ['sometimes', Rule::in(SaasPackageCatalog::ONBOARDING_LEVELS)],
            'support_level' => ['sometimes', Rule::in(SaasPackageCatalog::SUPPORT_LEVELS)],
            'feature_flags' => ['nullable', 'array'],
            'included_modules' => ['nullable', 'array'],
            'excluded_modules' => ['nullable', 'array'],
            'commercial_notes' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
