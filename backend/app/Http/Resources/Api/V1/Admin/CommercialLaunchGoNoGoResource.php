<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 20 — presents the commercial launch GO/WATCH/NO-GO report produced by
 * CommercialLaunchGoNoGoService. Aggregate, evidence-backed, and secret-safe.
 */
class CommercialLaunchGoNoGoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
