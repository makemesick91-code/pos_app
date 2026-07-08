<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionRenewalCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRenewalCandidate
 *
 * Sprint 24 — presents a subscription renewal candidate. No secrets are exposed.
 */
class SubscriptionRenewalCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_reference' => $this->candidate_reference,
            'run_id' => $this->run_id,
            'tenant_id' => $this->tenant_id,
            'tenant_subscription_id' => $this->tenant_subscription_id,
            'policy_id' => $this->policy_id,
            'status' => $this->status,
            'renewal_stage' => $this->renewal_stage,
            'current_subscription_status' => $this->current_subscription_status,
            'current_period_start' => optional($this->current_period_start)->toDateString(),
            'current_period_end' => optional($this->current_period_end)->toDateString(),
            'days_until_expiry' => $this->days_until_expiry,
            'grace_ends_at' => $this->grace_ends_at,
            'billing_invoice_id' => $this->billing_invoice_id,
            'billing_account_id' => $this->billing_account_id,
            'last_payment_evidence_status' => $this->last_payment_evidence_status,
            'priority' => $this->priority,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'qualified_for_manual_renewal_at' => $this->qualified_for_manual_renewal_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
