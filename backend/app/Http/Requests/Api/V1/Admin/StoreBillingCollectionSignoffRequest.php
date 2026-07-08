<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasBillingCollectionSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 23 — validates a billing collection sign-off. A REJECTED sign-off forces
 * NO-GO; an APPROVED_WITH_RISK sign-off forces WATCH.
 */
class StoreBillingCollectionSignoffRequest extends FormRequest
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
            'signoff_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_collection_signoffs,signoff_reference'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['required', Rule::in(SaasBillingCollectionSignoff::ROLES)],
            'decision' => ['required', Rule::in(SaasBillingCollectionSignoff::DECISIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
