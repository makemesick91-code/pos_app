<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UIX-4 — validates the tenant-owner browser login form.
 *
 * Intentionally minimal: presence + shape only. Credential verification, the
 * tenant-owner authorization predicate, and rate limiting live in the
 * controller so every failure mode returns the SAME generic message and no
 * account-existence information leaks through validation (UIX4-R013).
 */
class OwnerLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }
}
