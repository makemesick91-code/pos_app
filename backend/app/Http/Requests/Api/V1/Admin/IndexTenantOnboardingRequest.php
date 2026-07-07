<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\TenantOnboardingRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 12 — validates filters for the admin onboarding-run list.
 */
class IndexTenantOnboardingRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::in([
                TenantOnboardingRun::STATUS_PENDING,
                TenantOnboardingRun::STATUS_RUNNING,
                TenantOnboardingRun::STATUS_COMPLETED,
                TenantOnboardingRun::STATUS_FAILED,
            ])],
            'tenant_id' => ['sometimes', 'nullable', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
