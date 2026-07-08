<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 25 — presents a tenant's authoritative lifecycle decision plus the
 * active manual suspension (if any) and recent lifecycle events. The lifecycle
 * status is always computed server-side by TenantLifecycleService. No secrets.
 *
 * Wraps an array built by the controller: {tenant, decision, active_suspension,
 * events}.
 */
class TenantLifecycleResource extends JsonResource
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
            'tenant_code' => $data['tenant']->code,
            'lifecycle' => $data['decision']->toArray(),
            'active_suspension' => $data['active_suspension'] === null
                ? null
                : new TenantManualSuspensionResource($data['active_suspension']),
            'recent_events' => collect($data['events'])->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'previous_status' => $event->previous_status,
                'new_status' => $event->new_status,
                'reason_category' => $event->reason_category,
                'effective_at' => optional($event->effective_at)->toIso8601String(),
                'actor_user_id' => $event->actor_user_id,
            ])->all(),
        ];
    }
}
