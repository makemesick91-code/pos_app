<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsitePage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates creation of a public website page. Free-text is sanitized
 * in the service. No secrets are accepted.
 */
class StorePublicWebsitePageRequest extends FormRequest
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
            'page_key' => ['required', Rule::in(PublicWebsitePage::KEYS), 'unique:public_website_pages,page_key'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:public_website_pages,slug'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(PublicWebsitePage::STATUSES)],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'content_sections' => ['nullable', 'array'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
