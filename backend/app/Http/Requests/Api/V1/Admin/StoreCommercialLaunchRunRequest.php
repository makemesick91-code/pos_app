<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 20 — validates creation of a commercial launch run. All summaries are
 * computed by CommercialLaunchReadinessService; the caller supplies only window /
 * evidence / metadata references. No secret or credential is accepted.
 */
class StoreCommercialLaunchRunRequest extends FormRequest
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
            'launch_reference' => ['nullable', 'string', 'max:255'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date'],
            'evidence_references' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
