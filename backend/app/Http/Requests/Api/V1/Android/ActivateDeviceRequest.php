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
            // UIX-8C-07 — optional support/triage metadata. app_version is the
            // client build; installation_id is the app-generated installation id
            // (never a hardware id) and is stored ONLY as a hash server-side.
            'app_version' => ['nullable', 'string', 'max:40'],
            'installation_id' => ['nullable', 'string', 'max:191'],
            'store_id' => ['nullable', 'integer'],
            'register_id' => ['nullable', 'integer'],
        ];
    }
}
