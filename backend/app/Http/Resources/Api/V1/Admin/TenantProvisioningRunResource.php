<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantProvisioningRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 33 — the SAFE, redacted representation of a provisioning run (ONB-R024).
 * It carries ids, statuses, plan codes, trial window and the deterministic
 * checklist — never a password, token, owner email/name or any PII.
 *
 * @mixin TenantProvisioningRun
 */
class TenantProvisioningRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'onboarding_type' => $this->onboarding_type,
            'requested_plan_code' => $this->requested_plan_code,
            'resolved_plan_code' => $this->resolved_plan_code,
            'tenant_id' => $this->tenant_id,
            'owner_user_id' => $this->owner_user_id,
            'first_branch_id' => $this->first_branch_id,
            'first_cashier_user_id' => $this->first_cashier_user_id,
            'first_register_id' => $this->first_register_id,
            'first_device_id' => $this->first_device_id,
            'trial_starts_at' => optional($this->trial_starts_at)?->toIso8601String(),
            'trial_ends_at' => optional($this->trial_ends_at)?->toIso8601String(),
            'billing_period' => $this->billing_period,
            'tenant_billing_invoice_id' => $this->tenant_billing_invoice_id,
            'payment_intent_id' => $this->payment_intent_id,
            'checklist' => $this->checklist_json,
            'failure_reason' => $this->failure_reason,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'failed_at' => optional($this->failed_at)?->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
