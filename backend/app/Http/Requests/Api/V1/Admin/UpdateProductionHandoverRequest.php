<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 18 — validates updating a production handover package. Status is only
 * changed through mark-ready / mark-handed-over (conservative transitions), never
 * directly. candidate_commit/tag are references only.
 */
class UpdateProductionHandoverRequest extends FormRequest
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
            'candidate_commit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'candidate_tag' => ['sometimes', 'nullable', 'string', 'max:255'],
            'production_readiness_summary' => ['sometimes', 'nullable', 'array'],
            'operator_handover_summary' => ['sometimes', 'nullable', 'array'],
            'admin_handover_summary' => ['sometimes', 'nullable', 'array'],
            'support_sla_summary' => ['sometimes', 'nullable', 'array'],
            'backup_restore_summary' => ['sometimes', 'nullable', 'array'],
            'ownership_matrix' => ['sometimes', 'nullable', 'array'],
            'evidence_references' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
