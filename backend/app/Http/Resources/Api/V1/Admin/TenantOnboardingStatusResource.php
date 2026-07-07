<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 12 — the backend-generated onboarding status/checklist for a tenant.
 * The checklist is set on the tenant by the controller from
 * TenantOnboardingChecklistService and is never trusted from the client.
 *
 * @mixin Tenant
 */
class TenantOnboardingStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, bool> $checklist */
        $checklist = $this->resource->getAttribute('onboarding_checklist') ?? [];

        return [
            'tenant' => [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
                'status' => $this->status,
            ],
            'checklist' => $checklist,
            'complete' => $checklist !== [] && ! in_array(false, [
                $checklist['tenant_created'] ?? false,
                $checklist['default_store_created'] ?? false,
                $checklist['owner_user_created'] ?? false,
                $checklist['subscription_assigned'] ?? false,
            ], true),
        ];
    }
}
