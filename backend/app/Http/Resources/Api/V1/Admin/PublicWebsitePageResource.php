<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PublicWebsitePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicWebsitePage
 *
 * Sprint 21 — presents a public website page. No secrets are exposed.
 */
class PublicWebsitePageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_key' => $this->page_key,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'content_sections' => $this->content_sections,
            'published_at' => $this->published_at,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
