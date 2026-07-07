<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasPackageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates creation of a SaaS package catalog entry. Pricing here is
 * governance metadata only; it activates no real billing and never bypasses the
 * subscription/device runtime enforcement. No secret is accepted.
 */
class StoreSaasPackageCatalogRequest extends FormRequest
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
            'package_code' => ['nullable', 'string', 'max:255', 'unique:saas_package_catalogs,package_code'],
            'name' => ['required', 'string', 'max:255'],
            'target_segment' => ['required', Rule::in(SaasPackageCatalog::SEGMENTS)],
            'status' => ['nullable', Rule::in(SaasPackageCatalog::STATUSES)],
            'monthly_price' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'device_limit' => ['nullable', 'integer', 'min:0'],
            'store_limit' => ['nullable', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'onboarding_level' => ['nullable', Rule::in(SaasPackageCatalog::ONBOARDING_LEVELS)],
            'support_level' => ['nullable', Rule::in(SaasPackageCatalog::SUPPORT_LEVELS)],
            'feature_flags' => ['nullable', 'array'],
            'included_modules' => ['nullable', 'array'],
            'excluded_modules' => ['nullable', 'array'],
            'commercial_notes' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
