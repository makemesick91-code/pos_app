<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SaasPackageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates filters for listing SaaS package catalog entries.
 */
class IndexSaasPackageCatalogRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(SaasPackageCatalog::STATUSES)],
            'target_segment' => ['nullable', Rule::in(SaasPackageCatalog::SEGMENTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
