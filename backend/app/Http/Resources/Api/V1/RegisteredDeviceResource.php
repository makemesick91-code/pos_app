<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RegisteredDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Presents a tenant-owned registered device (Sprint 10). Never exposes another
 * tenant's device.
 *
 * @mixin RegisteredDevice
 */
class RegisteredDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'device_uuid' => $this->device_uuid,
            'device_name' => $this->device_name,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'status' => $this->status,
            'registered_at' => $this->registered_at,
            'last_seen_at' => $this->last_seen_at,
            'revoked_at' => $this->revoked_at,
        ];
    }
}
