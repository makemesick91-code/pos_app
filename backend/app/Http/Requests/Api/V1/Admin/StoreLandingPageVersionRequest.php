<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\LandingPageVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates creation of a landing page version. The CTA target is
 * validated against the allowed interest-only targets in the service. Free-text
 * is sanitized. No secrets or live tracking tokens are accepted.
 */
class StoreLandingPageVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version_reference' => ['nullable', 'string', 'max:255', 'unique:landing_page_versions,version_reference'],
            'status' => ['nullable', Rule::in(LandingPageVersion::STATUSES)],
            'headline' => ['required', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:500'],
            'hero_cta_label' => ['nullable', 'string', 'max:120'],
            'hero_cta_target' => ['nullable', 'string', 'max:255'],
            'target_segments' => ['nullable', 'array'],
            'package_highlights' => ['nullable', 'array'],
            'feature_highlights' => ['nullable', 'array'],
            'proof_points' => ['nullable', 'array'],
            'faq_items' => ['nullable', 'array'],
            'seo_summary' => ['nullable', 'array'],
            'privacy_summary' => ['nullable', 'array'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
