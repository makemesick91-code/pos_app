<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 25 — validates a platform-admin lift of a manual tenant suspension.
 * Reason is mandatory so every reactivation is accountable (TLS-R006). Lifting
 * is the only way to clear a manual suspension; automation can never do it.
 */
class LiftTenantSuspensionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
