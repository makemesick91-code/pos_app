<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 18 — validates creation of a pilot closure run. Summaries are computed
 * by PilotClosureService from the defect/accepted-risk/stabilization review; the
 * request only carries the closure window and optional evidence references. No
 * secret is ever accepted as a dedicated field.
 */
class StorePilotClosureRequest extends FormRequest
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
            'closure_reference' => ['nullable', 'string', 'max:255'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date'],
            'evidence_references' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
