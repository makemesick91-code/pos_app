<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RegisteredDevice;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for listing the authenticated tenant's registered devices (Sprint 10).
 * All filters are optional and always scoped to the tenant from context — a
 * client can never list another tenant's devices.
 */
class IndexDeviceRequest extends FormRequest
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
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    RegisteredDevice::STATUS_ACTIVE,
                    RegisteredDevice::STATUS_REVOKED,
                    RegisteredDevice::STATUS_BLOCKED,
                ]),
            ],
        ];
    }
}
