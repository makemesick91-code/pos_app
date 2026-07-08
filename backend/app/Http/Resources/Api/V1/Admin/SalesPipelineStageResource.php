<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalesPipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesPipelineStage
 *
 * Sprint 22 — presents a sales pipeline stage. No secrets are exposed.
 */
class SalesPipelineStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage_code' => $this->stage_code,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'is_terminal' => $this->is_terminal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
