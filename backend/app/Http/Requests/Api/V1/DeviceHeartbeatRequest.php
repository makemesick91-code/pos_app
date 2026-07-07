<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a device heartbeat request (Sprint 10). The device_uuid identifies
 * a tenant-owned device whose last_seen_at is refreshed; the tenant is always
 * resolved from context.
 */
class DeviceHeartbeatRequest extends FormRequest
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
            'device_uuid' => ['required', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
