<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UIX-3 — validates the platform-admin browser login form.
 *
 * Intentionally minimal: presence + shape only. Authentication (credential
 * verification, platform-admin authorization, rate limiting) is performed in the
 * controller so that every failure mode returns the SAME generic message and no
 * account-existence information leaks through validation.
 */
class AdminLoginRequest extends FormRequest
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
