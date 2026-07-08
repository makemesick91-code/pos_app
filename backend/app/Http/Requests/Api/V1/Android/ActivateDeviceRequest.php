<?php

namespace App\Http\Requests\Api\V1\Android;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 34 — validates an Android device activation request. The raw activation
 * token is validated for length only and is NEVER echoed back (ADR-R003).
 */
class ActivateDeviceRequest extends FormRequest
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
            'activation_token' => ['required', 'string', 'min:8', 'max:128'],
            'device_fingerprint' => ['required', 'string', 'min:8', 'max:191'],
            'device_uuid' => ['nullable', 'string', 'max:191'],
            'device_label' => ['nullable', 'string', 'max:120'],
            'store_id' => ['nullable', 'integer'],
            'register_id' => ['nullable', 'integer'],
        ];
    }
}
