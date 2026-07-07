<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — tenant summary row for the admin tenant list. Exposes counts and
 * an authoritative subscription summary (set by the controller/service). Never
 * exposes secrets or raw payment gateway payloads.
 *
 * @mixin Tenant
 */
class AdminTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed>|null $summary */
        $summary = $this->resource->getAttribute('subscription_summary');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'stores_count' => (int) ($this->getAttribute('stores_count') ?? 0),
            'devices_active_count' => (int) ($this->getAttribute('devices_active_count') ?? 0),
            'subscription' => $summary === null ? null : [
                'status' => $summary['status'] ?? null,
                'allowed' => $summary['allowed'] ?? null,
                'plan_code' => $summary['plan_code'] ?? null,
                'ends_at' => $summary['ends_at'] ?? null,
            ],
        ];
    }
}
