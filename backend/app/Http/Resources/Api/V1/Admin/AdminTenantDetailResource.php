<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — full tenant detail for admin. Includes stores, an authoritative
 * subscription summary, and device counts. Never exposes secrets or raw payment
 * gateway payloads.
 *
 * @mixin Tenant
 */
class AdminTenantDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed>|null $summary */
        $summary = $this->resource->getAttribute('subscription_summary');
        $activeDevices = (int) ($this->resource->getAttribute('devices_active_count') ?? 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'owner_name' => $this->owner_name,
            'status' => $this->status,
            'stores' => $this->whenLoaded('stores', fn () => $this->stores->map(fn ($store) => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'is_active' => (bool) $store->is_active,
            ])->values()),
            'subscription' => $summary,
            'devices' => [
                'active_count' => $activeDevices,
                'max_devices' => $summary['max_devices'] ?? null,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
