<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\LeadInterestSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadInterestSubmission
 *
 * Sprint 21 — presents an interest-only lead submission. Interest-only metadata;
 * no secrets and no over-exposed internal fields.
 */
class LeadInterestSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_reference' => $this->lead_reference,
            'status' => $this->status,
            'business_name' => $this->business_name,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'business_type' => $this->business_type,
            'estimated_store_count' => $this->estimated_store_count,
            'estimated_device_count' => $this->estimated_device_count,
            'interest_package_code' => $this->interest_package_code,
            'message' => $this->message,
            'source' => $this->source,
            'consent_accepted_at' => $this->consent_accepted_at,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
