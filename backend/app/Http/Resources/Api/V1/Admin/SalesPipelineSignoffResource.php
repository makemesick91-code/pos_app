<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalesPipelineSignoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesPipelineSignoff
 *
 * Sprint 22 — presents a sales pipeline sign-off. No secrets are exposed.
 */
class SalesPipelineSignoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'signoff_reference' => $this->signoff_reference,
            'signer_user_id' => $this->signer_user_id,
            'signer_name' => $this->signer_name,
            'signer_role' => $this->signer_role,
            'decision' => $this->decision,
            'notes' => $this->notes,
            'evidence_reference' => $this->evidence_reference,
            'signed_at' => $this->signed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
