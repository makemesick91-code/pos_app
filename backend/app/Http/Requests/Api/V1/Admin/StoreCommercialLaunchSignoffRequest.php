<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\CommercialLaunchSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates recording a commercial launch signoff. A REJECTED signoff
 * forces the launch NO-GO; an APPROVED_WITH_RISK signoff forces WATCH.
 */
class StoreCommercialLaunchSignoffRequest extends FormRequest
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
            'signoff_reference' => ['nullable', 'string', 'max:255', 'unique:commercial_launch_signoffs,signoff_reference'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['required', Rule::in(CommercialLaunchSignoff::ROLES)],
            'decision' => ['required', Rule::in(CommercialLaunchSignoff::DECISIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
