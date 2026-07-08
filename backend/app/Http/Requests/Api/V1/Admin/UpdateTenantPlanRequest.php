<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 26 — validates a platform-admin tenant plan catalogue update.
 */
class UpdateTenantPlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'billing_interval' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
