<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates updating a sales lead. A lead never creates a
 * tenant/user/subscription/device.
 */
class UpdateSalesLeadRequest extends FormRequest
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
            'business_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'estimated_store_count' => ['nullable', 'integer', 'min:0'],
            'estimated_device_count' => ['nullable', 'integer', 'min:0'],
            'interest_package_code' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(SalesLead::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
