<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PublicWebsitePage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates public website page listing filters.
 */
class IndexPublicWebsitePageRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(PublicWebsitePage::STATUSES)],
            'page_key' => ['nullable', Rule::in(PublicWebsitePage::KEYS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
