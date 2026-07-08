<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates manual creation of a sales lead. Free-text is sanitized in
 * the service. A lead never creates a tenant/user/subscription/device.
 */
class StoreSalesLeadRequest extends FormRequest
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
            'lead_reference' => ['nullable', 'string', 'max:255', 'unique:sales_leads,lead_reference'],
            'source' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'estimated_store_count' => ['nullable', 'integer', 'min:0'],
            'estimated_device_count' => ['nullable', 'integer', 'min:0'],
            'interest_package_code' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(SalesLead::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
