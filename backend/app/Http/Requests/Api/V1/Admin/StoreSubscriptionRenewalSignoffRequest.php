<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionRenewalSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 24 — validates adding a subscription renewal sign-off.
 */
class StoreSubscriptionRenewalSignoffRequest extends FormRequest
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
            'signoff_reference' => ['nullable', 'string', 'max:255', 'unique:subscription_renewal_signoffs,signoff_reference'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['required', Rule::in(SubscriptionRenewalSignoff::ROLES)],
            'decision' => ['required', Rule::in(SubscriptionRenewalSignoff::DECISIONS)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
