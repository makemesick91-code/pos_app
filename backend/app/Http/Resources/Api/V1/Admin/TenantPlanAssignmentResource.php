<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 26 — presents a tenant's resolved plan decision plus the active
 * assignment (if any). The plan is always resolved server-side by
 * TenantPlanResolver. No secrets.
 *
 * Wraps an array built by the controller: {tenant, decision, assignment}.
 */
class TenantPlanAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;
        $assignment = $data['assignment'] ?? null;

        return [
            'tenant_id' => $data['tenant']->id,
            'tenant_code' => $data['tenant']->code,
            'plan' => $data['decision']->toArray(),
            'active_assignment' => $assignment === null ? null : [
                'id' => $assignment->id,
                'plan_id' => $assignment->tenant_plan_id,
                'status' => $assignment->status,
                'source' => $assignment->source,
                'effective_from' => optional($assignment->effective_from)->toIso8601String(),
                'effective_until' => optional($assignment->effective_until)->toIso8601String(),
                'assigned_by_user_id' => $assignment->assigned_by_user_id,
            ],
        ];
    }
}
