<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantManualSuspension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantManualSuspension
 *
 * Sprint 25 — presents a manual tenant suspension record. Reason is sanitized at
 * write time; no secrets are exposed.
 */
class TenantManualSuspensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'status' => $this->status,
            'reason' => $this->reason,
            'reason_category' => $this->reason_category,
            'effective_at' => optional($this->effective_at)->toIso8601String(),
            'lifted_at' => optional($this->lifted_at)->toIso8601String(),
            'lift_reason' => $this->lift_reason,
            'suspended_by_user_id' => $this->suspended_by_user_id,
            'lifted_by_user_id' => $this->lifted_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
