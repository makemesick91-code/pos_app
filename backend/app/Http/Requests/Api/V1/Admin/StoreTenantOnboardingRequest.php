<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\TenantOnboardingRun;
use App\Models\TenantSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 12 — validates a platform-admin tenant onboarding request.
 *
 * The client may never assign a tenant_id — it is created by the backend. The
 * owner_password is write-only input: it is hashed by the service and never
 * stored in metadata/checklist/audit output.
 */
class StoreTenantOnboardingRequest extends FormRequest
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
        // On an idempotent replay (same onboarding_reference) the backend returns
        // the existing run without creating anything, so the tenant_code/
        // owner_email uniqueness checks (which the first run's own records would
        // now fail) must be skipped. A genuinely new reference still enforces them.
        $isReplay = TenantOnboardingRun::query()
            ->where('onboarding_reference', (string) $this->input('onboarding_reference'))
            ->exists();

        return [
            'onboarding_reference' => ['required', 'string', 'max:120'],
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_code' => ['required', 'string', 'max:64', ...($isReplay ? [] : ['unique:tenants,code'])],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'store_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', ...($isReplay ? [] : ['unique:users,email'])],
            'owner_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'owner_password' => ['required', 'string', 'min:8', 'max:255'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'subscription_status' => ['sometimes', Rule::in(self::statuses())],
            'trial_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'demo_data_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            TenantSubscription::STATUS_TRIAL,
            TenantSubscription::STATUS_ACTIVE,
            TenantSubscription::STATUS_GRACE,
        ];
    }
}
