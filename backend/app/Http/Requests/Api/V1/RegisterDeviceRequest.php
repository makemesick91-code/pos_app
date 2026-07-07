<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RegisteredDevice;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a device registration request (Sprint 10). Only the device
 * identity/metadata and an optional store are accepted from the client —
 * tenant_id comes from context and user_id from the authenticated user. The
 * store, if provided, must belong to the authenticated tenant. platform is
 * pinned to ANDROID for Sprint 10.
 */
class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->hasTenant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'device_uuid' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', Rule::in([RegisteredDevice::PLATFORM_ANDROID])],
            'app_version' => ['nullable', 'string', 'max:40'],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
