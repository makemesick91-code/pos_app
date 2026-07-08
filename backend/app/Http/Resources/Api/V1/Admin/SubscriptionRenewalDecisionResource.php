<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionRenewalDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRenewalDecision
 *
 * Sprint 24 — presents a subscription renewal decision. No secrets are exposed.
 */
class SubscriptionRenewalDecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decision_reference' => $this->decision_reference,
            'candidate_id' => $this->candidate_id,
            'tenant_id' => $this->tenant_id,
            'tenant_subscription_id' => $this->tenant_subscription_id,
            'decision' => $this->decision,
            'status' => $this->status,
            'decided_by_user_id' => $this->decided_by_user_id,
            'decided_at' => $this->decided_at,
            'effective_start_date' => optional($this->effective_start_date)->toDateString(),
            'effective_end_date' => optional($this->effective_end_date)->toDateString(),
            'approved_plan_id' => $this->approved_plan_id,
            'manual_billing_invoice_id' => $this->manual_billing_invoice_id,
            'reason' => $this->reason,
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
