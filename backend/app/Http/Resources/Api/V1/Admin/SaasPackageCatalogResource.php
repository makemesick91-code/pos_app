<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasPackageCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasPackageCatalog
 *
 * Sprint 20 — presents a SaaS package catalog entry. Pricing is governance
 * metadata only; no secrets are exposed.
 */
class SaasPackageCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_code' => $this->package_code,
            'name' => $this->name,
            'target_segment' => $this->target_segment,
            'status' => $this->status,
            'monthly_price' => $this->monthly_price,
            'currency' => $this->currency,
            'device_limit' => $this->device_limit,
            'store_limit' => $this->store_limit,
            'user_limit' => $this->user_limit,
            'onboarding_level' => $this->onboarding_level,
            'support_level' => $this->support_level,
            'feature_flags' => $this->feature_flags,
            'included_modules' => $this->included_modules,
            'excluded_modules' => $this->excluded_modules,
            'commercial_notes' => $this->commercial_notes,
            'evidence_reference' => $this->evidence_reference,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
