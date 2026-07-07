<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 21 — validates accepting a public website risk. A reason is always
 * required; the service enforces approver + expiry for CRITICAL/HIGH/MEDIUM.
 */
class AcceptPublicWebsiteRiskRequest extends FormRequest
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
