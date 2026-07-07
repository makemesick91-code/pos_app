<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 11 — validates admin plan creation. The code is unique;
 * max_devices/max_stores must be positive. Plans are backend-owned and never
 * created from tenant/client input.
 */
class StoreAdminSubscriptionPlanRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64', 'unique:subscription_plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price_monthly' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_stores' => ['required', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1'],
            'max_products' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
