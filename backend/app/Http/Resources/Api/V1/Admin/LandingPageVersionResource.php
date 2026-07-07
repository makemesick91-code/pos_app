<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\LandingPageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LandingPageVersion
 *
 * Sprint 21 — presents a landing page version. No secrets are exposed.
 */
class LandingPageVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_reference' => $this->version_reference,
            'status' => $this->status,
            'headline' => $this->headline,
            'subheadline' => $this->subheadline,
            'hero_cta_label' => $this->hero_cta_label,
            'hero_cta_target' => $this->hero_cta_target,
            'target_segments' => $this->target_segments,
            'package_highlights' => $this->package_highlights,
            'feature_highlights' => $this->feature_highlights,
            'proof_points' => $this->proof_points,
            'faq_items' => $this->faq_items,
            'seo_summary' => $this->seo_summary,
            'privacy_summary' => $this->privacy_summary,
            'evidence_reference' => $this->evidence_reference,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
