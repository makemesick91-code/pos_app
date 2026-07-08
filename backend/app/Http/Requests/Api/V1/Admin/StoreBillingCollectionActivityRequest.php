<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingCollectionActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates a manual collection activity. WHATSAPP_MANUAL / EMAIL_MANUAL
 * are notes only; no real message is ever sent.
 */
class StoreBillingCollectionActivityRequest extends FormRequest
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
            'activity_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_collection_activities,activity_reference'],
            'billing_account_id' => ['nullable', 'integer', 'exists:saas_billing_accounts,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'activity_type' => ['required', Rule::in(SaasBillingCollectionActivity::ACTIVITY_TYPES)],
            'status' => ['nullable', Rule::in(SaasBillingCollectionActivity::STATUSES)],
            'summary' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
