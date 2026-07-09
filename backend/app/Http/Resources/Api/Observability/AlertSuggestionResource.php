<?php

namespace App\Http\Resources\Api\Observability;

use App\Models\ObservabilityAlertSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe alert-suggestion representation (OBS-R004/R018).
 */
class AlertSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ObservabilityAlertSuggestion $suggestion */
        $suggestion = $this->resource;

        return $suggestion->toSafeArray();
    }
}
