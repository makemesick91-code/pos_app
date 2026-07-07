<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\CommercialLaunchRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommercialLaunchRun
 *
 * Sprint 20 — presents a commercial launch run. Summaries are aggregate only; no
 * secrets are exposed.
 */
class CommercialLaunchRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'launch_reference' => $this->launch_reference,
            'status' => $this->status,
            'decision' => $this->decision,
            'window_start' => $this->window_start,
            'window_end' => $this->window_end,
            'package_summary' => $this->package_summary,
            'pricing_summary' => $this->pricing_summary,
            'sales_enablement_summary' => $this->sales_enablement_summary,
            'onboarding_capacity_summary' => $this->onboarding_capacity_summary,
            'risk_summary' => $this->risk_summary,
            'signoff_summary' => $this->signoff_summary,
            'evidence_references' => $this->evidence_references,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
