<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantOnboardingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 12 — serializes a tenant onboarding run for platform admins. Exposes
 * the created tenant/store/owner/subscription references and the backend-derived
 * checklist. Never exposes the owner password or any secret; the demo manifest
 * ids are internal and are not surfaced here.
 *
 * @mixin TenantOnboardingRun
 */
class TenantOnboardingRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'onboarding_reference' => $this->onboarding_reference,
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'tenant' => $this->tenant_id === null ? null : [
                'id' => $this->tenant_id,
                'name' => $this->tenant_name,
            ],
            'default_store' => $this->default_store_id === null ? null : [
                'id' => $this->default_store_id,
                'name' => $this->store_name,
            ],
            'owner_user' => $this->owner_user_id === null ? null : [
                'id' => $this->owner_user_id,
                'email' => $this->owner_email,
            ],
            'subscription' => $this->tenant_subscription_id === null ? null : [
                'id' => $this->tenant_subscription_id,
                'subscription_plan_id' => $this->subscription_plan_id,
                'status' => $this->whenLoaded('tenantSubscription', fn () => $this->tenantSubscription?->status),
            ],
            'demo_data_enabled' => (bool) $this->demo_data_enabled,
            'demo_data_seeded_at' => optional($this->demo_data_seeded_at)->toIso8601String(),
            'demo_data_reset_at' => optional($this->demo_data_reset_at)->toIso8601String(),
            'checklist' => $this->checklist,
            'error_message' => $this->error_message,
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
