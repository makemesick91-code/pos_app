<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingPaymentEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingPaymentEvidence
 *
 * Sprint 23 — presents a SaaS billing manual payment evidence. No payment gateway
 * payloads or secrets are exposed.
 */
class BillingPaymentEvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_reference' => $this->payment_reference,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at,
            'received_by_user_id' => $this->received_by_user_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at,
            'rejected_reason' => $this->rejected_reason,
            'evidence_label' => $this->evidence_label,
            'evidence_reference' => $this->evidence_reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
