<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 11 — filters for the admin tenant index. Authorization is enforced by
 * the platform.admin middleware; this request only validates query filters.
 */
class IndexAdminTenantRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in([
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_SUSPENDED,
                Tenant::STATUS_INACTIVE,
            ])],
            'subscription_status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
