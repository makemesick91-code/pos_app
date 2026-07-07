<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsitePage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates update of a public website page.
 */
class UpdatePublicWebsitePageRequest extends FormRequest
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
        $page = $this->route('page');
        $id = is_object($page) ? $page->id : $page;

        return [
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('public_website_pages', 'slug')->ignore($id)],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(PublicWebsitePage::STATUSES)],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'content_sections' => ['nullable', 'array'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
