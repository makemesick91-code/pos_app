<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 17 — accept a pilot defect as a known risk. Requires a reason; an
 * expiry/review date is required for blocking/major severities (enforced by
 * AcceptedRiskGovernanceService). The original severity is always preserved.
 */
class AcceptPilotDefectRiskRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:2000'],
            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
