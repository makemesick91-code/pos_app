<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 26 — presents a tenant's usage limits with current usage/remaining,
 * always computed server-side from real DB counts. Non-meterable limits are
 * reported explicitly. No secrets.
 *
 * Wraps an array built by the controller: {tenant, plan_key, limits}.
 */
class TenantUsageLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'tenant_id' => $data['tenant']->id,
            'plan_key' => $data['plan_key'],
            'limits' => $data['limits'],
        ];
    }
}
