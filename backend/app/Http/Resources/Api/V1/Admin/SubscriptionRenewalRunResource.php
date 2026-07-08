<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionRenewalRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRenewalRun
 *
 * Sprint 24 — presents a subscription renewal run. No secrets are exposed.
 */
class SubscriptionRenewalRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_reference' => $this->run_reference,
            'policy_id' => $this->policy_id,
            'status' => $this->status,
            'run_date' => optional($this->run_date)->toDateString(),
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'summary' => $this->summary,
            'created_by_user_id' => $this->created_by_user_id,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
