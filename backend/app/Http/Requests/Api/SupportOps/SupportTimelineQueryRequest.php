<?php

namespace App\Http\Requests\Api\SupportOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 35 — validates diagnostic timeline query filters (SUP-R020).
 */
class SupportTimelineQueryRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', Rule::in((array) config('support_operations_governance.timeline.sources', []))],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('support_operations_governance.timeline.max_limit', 500)],
        ];
    }
}
