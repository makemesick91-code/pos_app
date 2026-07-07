<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 21 — presents the public website GO/WATCH/NO-GO report produced by
 * PublicWebsiteGoNoGoService. Aggregate, evidence-backed, and secret-safe.
 */
class PublicWebsiteGoNoGoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
