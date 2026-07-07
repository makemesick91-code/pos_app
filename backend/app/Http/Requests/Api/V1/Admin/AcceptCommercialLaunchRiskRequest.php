<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 20 — validates accepting a commercial launch risk. For CRITICAL/HIGH/
 * MEDIUM the service additionally enforces an approver and an expiry.
 */
class AcceptCommercialLaunchRiskRequest extends FormRequest
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
