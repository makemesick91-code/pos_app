<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingAccount
 *
 * Sprint 23 — presents a SaaS billing account. No secrets are exposed.
 */
class BillingAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_reference' => $this->account_reference,
            'tenant_id' => $this->tenant_id,
            'billing_name' => $this->billing_name,
            'billing_email' => $this->billing_email,
            'billing_phone' => $this->billing_phone,
            'billing_address' => $this->billing_address,
            'tax_identifier' => $this->tax_identifier,
            'status' => $this->status,
            'billing_currency' => $this->billing_currency,
            'payment_terms_days' => $this->payment_terms_days,
            'collection_owner_user_id' => $this->collection_owner_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
