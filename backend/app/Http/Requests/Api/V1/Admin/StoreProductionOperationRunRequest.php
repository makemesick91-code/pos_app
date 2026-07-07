<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 19 — validates creation of a production operation run. The health,
 * incident, backup/restore, support-SLA, and release/rollback summaries are
 * computed by the service; the request only accepts a window and evidence.
 * No secret is ever accepted as a dedicated field.
 */
class StoreProductionOperationRunRequest extends FormRequest
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
            'operation_reference' => ['sometimes', 'string', 'max:255'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date'],
            'evidence_references' => ['nullable', 'array'],
            'maintenance_summary' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
