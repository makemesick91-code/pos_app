<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingCycle
 *
 * Sprint 23 — presents a SaaS billing cycle. No secrets are exposed.
 */
class BillingCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cycle_reference' => $this->cycle_reference,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'status' => $this->status,
            'billing_month' => $this->billing_month,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
