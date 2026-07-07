<?php

namespace App\Http\Requests\PublicWebsite;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 21 — validates a public interest-only lead submission. Consent is
 * required. This request captures INTEREST only — it never provisions a tenant/
 * user/subscription/device. Secret-looking input is stripped in the service.
 */
class StoreLeadInterestRequest extends FormRequest
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
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'estimated_store_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'estimated_device_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'interest_package_code' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }
}
