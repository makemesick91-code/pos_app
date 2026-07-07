<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionHandoverSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 18 — validates an append-only production handover sign-off. The signer
 * role and decision are constrained to the known enums; a REJECTED decision
 * forces NO_GO and APPROVED_WITH_RISK forces WATCH downstream. No secret field
 * is accepted.
 */
class StoreProductionHandoverSignoffRequest extends FormRequest
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
            'signer_role' => ['required', Rule::in(ProductionHandoverSignoff::ROLES)],
            'decision' => ['required', Rule::in(ProductionHandoverSignoff::DECISIONS)],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
