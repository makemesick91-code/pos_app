<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 33 — validates a platform-admin onboarding start request. The plan must
 * be a known plan (fail closed — ONB-R003); an idempotency key is required for
 * the mutation (ONB-R005). No feature/limit override is accepted; the request
 * never carries an invoice amount (amounts come from plan pricing). Owner PII is
 * validated but never echoed back in any response (ONB-R024).
 */
class StartTenantOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already behind platform.admin middleware; deny by default here
        // only if that ever changes.
        return $this->user()?->isPlatformAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $min = (int) config('onboarding_governance.idempotency.key_min_length', 8);
        $max = (int) config('onboarding_governance.idempotency.key_max_length', 128);
        $planKeys = (array) config('tenant_plan.plan_keys', []);

        return [
            'idempotency_key' => ['required', 'string', "min:{$min}", "max:{$max}"],
            'plan_code' => ['required', 'string', Rule::in($planKeys)],
            'tenant_name' => ['required', 'string', 'max:150'],
            'tenant_code' => ['nullable', 'string', 'max:40'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['nullable', 'email', 'max:180'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'first_branch_name' => ['required', 'string', 'max:150'],
            'first_branch_code' => ['nullable', 'string', 'max:40'],
            'first_cashier_name' => ['nullable', 'string', 'max:150'],
            'first_register_name' => ['nullable', 'string', 'max:150'],
            'with_trial' => ['nullable', 'boolean'],
            'with_cashier' => ['nullable', 'boolean'],
            'with_register' => ['nullable', 'boolean'],
            'with_invoice' => ['nullable', 'boolean'],
            'with_payment_intent' => ['nullable', 'boolean'],
            'onboarding_type' => ['nullable', 'string', Rule::in(['platform_admin', 'import_seed', 'internal'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
