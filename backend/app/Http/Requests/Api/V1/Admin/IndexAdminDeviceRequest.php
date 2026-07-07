<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\RegisteredDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 11 — filters for the admin device index (scoped to one tenant by route).
 */
class IndexAdminDeviceRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::in([
                RegisteredDevice::STATUS_ACTIVE,
                RegisteredDevice::STATUS_REVOKED,
                RegisteredDevice::STATUS_BLOCKED,
            ])],
        ];
    }
}
