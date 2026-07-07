<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsiteSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates recording a public website signoff. Preserved record; no
 * secrets. A REJECTED signoff forces NO-GO; APPROVED_WITH_RISK forces WATCH.
 */
class StorePublicWebsiteSignoffRequest extends FormRequest
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
            'signoff_reference' => ['nullable', 'string', 'max:255', 'unique:public_website_signoffs,signoff_reference'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['required', Rule::in(PublicWebsiteSignoff::ROLES)],
            'decision' => ['required', Rule::in(PublicWebsiteSignoff::DECISIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
