<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ProductionHandoverSignoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionHandoverSignoff
 *
 * Sprint 18 — presents an append-only production handover sign-off record. No
 * secret is exposed.
 */
class ProductionHandoverSignoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'production_handover_package_id' => $this->production_handover_package_id,
            'signoff_reference' => $this->signoff_reference,
            'signer_user_id' => $this->signer_user_id,
            'signer_name' => $this->signer_name,
            'signer_role' => $this->signer_role,
            'decision' => $this->decision,
            'notes' => $this->notes,
            'evidence_reference' => $this->evidence_reference,
            'signed_at' => $this->signed_at,
            'created_at' => $this->created_at,
        ];
    }
}
