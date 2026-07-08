<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionDunningNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates preparing a MANUAL subscription dunning notice. No real
 * message is ever sent; free-text is sanitized in the service.
 */
class StoreSubscriptionDunningNoticeRequest extends FormRequest
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
            'notice_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_dunning_notices,notice_reference'],
            'notice_type' => ['required', Rule::in(SubscriptionDunningNotice::TYPES)],
            'channel' => ['nullable', Rule::in(SubscriptionDunningNotice::CHANNELS)],
            'scheduled_for' => ['nullable', 'date'],
            'billing_invoice_id' => ['nullable', 'integer', 'exists:saas_billing_invoices,id'],
            'summary' => ['required', 'string', 'max:255'],
            'message_template_key' => ['nullable', 'string', 'max:255'],
            'manual_message_preview' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
