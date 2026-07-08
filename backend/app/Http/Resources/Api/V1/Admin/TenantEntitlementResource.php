<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 26 — presents a tenant's effective feature entitlement map (plan grants
 * with active overrides applied), always computed server-side. No secrets.
 *
 * Wraps an array built by the controller: {tenant, plan_key, entitlements,
 * overrides}.
 */
class TenantEntitlementResource extends JsonResource
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
            'entitlements' => $data['entitlements'],
            'active_overrides' => collect($data['overrides'])->map(fn ($o) => [
                'id' => $o->id,
                'entitlement_key' => $o->entitlement_key,
                'enabled' => (bool) $o->enabled,
                'reason_category' => $o->reason_category,
                'effective_from' => optional($o->effective_from)->toIso8601String(),
                'effective_until' => optional($o->effective_until)->toIso8601String(),
                'actor_user_id' => $o->actor_user_id,
            ])->values(),
        ];
    }
}
