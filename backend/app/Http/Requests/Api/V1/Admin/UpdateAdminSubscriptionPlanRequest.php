<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 11 — validates admin plan update. The code stays unique (ignoring the
 * plan being edited); positive limits are enforced. There is no hard delete.
 */
class UpdateAdminSubscriptionPlanRequest extends FormRequest
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
        $planId = $this->route('plan')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('subscription_plans', 'code')->ignore($planId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price_monthly' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_stores' => ['sometimes', 'integer', 'min:1'],
            'max_devices' => ['sometimes', 'integer', 'min:1'],
            'max_products' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
