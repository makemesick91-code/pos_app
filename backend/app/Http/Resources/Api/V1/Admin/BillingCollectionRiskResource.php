<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingCollectionRisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingCollectionRisk
 *
 * Sprint 23 — presents a SaaS billing collection risk. No secrets are exposed.
 */
class BillingCollectionRiskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'risk_reference' => $this->risk_reference,
            'billing_account_id' => $this->billing_account_id,
            'invoice_id' => $this->invoice_id,
            'area' => $this->area,
            'severity' => $this->severity,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'owner_user_id' => $this->owner_user_id,
            'mitigation' => $this->mitigation,
            'accepted_risk_at' => $this->accepted_risk_at,
            'accepted_risk_by' => $this->accepted_risk_by,
            'accepted_risk_reason' => $this->accepted_risk_reason,
            'accepted_risk_expires_at' => $this->accepted_risk_expires_at,
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
