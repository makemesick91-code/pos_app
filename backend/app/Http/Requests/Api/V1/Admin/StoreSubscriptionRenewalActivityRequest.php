<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates creating a subscription renewal activity. A manual
 * WhatsApp/email activity is only an internal record — no real message is sent.
 */
class StoreSubscriptionRenewalActivityRequest extends FormRequest
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
            'activity_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_activities,activity_reference'],
            'candidate_id' => ['nullable', 'integer', 'exists:subscription_renewal_candidates,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_subscription_id' => ['nullable', 'integer', 'exists:tenant_subscriptions,id'],
            'activity_type' => ['required', Rule::in(SubscriptionRenewalActivity::TYPES)],
            'status' => ['nullable', Rule::in(SubscriptionRenewalActivity::STATUSES)],
            'summary' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
