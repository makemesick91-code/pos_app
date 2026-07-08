<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 24 — validates accepting a subscription renewal risk. The service
 * enforces reason + approver + expiry for CRITICAL/HIGH/MEDIUM.
 */
class AcceptSubscriptionRenewalRiskRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:5000'],
            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
