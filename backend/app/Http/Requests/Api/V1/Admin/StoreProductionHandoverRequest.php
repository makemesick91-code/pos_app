<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 18 — validates creation of a production handover package. candidate_commit
 * and candidate_tag are references only; no deployment credential or secret is
 * accepted. Readiness is computed by ProductionHandoverService from the handover
 * documentation contract.
 */
class StoreProductionHandoverRequest extends FormRequest
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
            'handover_reference' => ['nullable', 'string', 'max:255'],
            'pilot_closure_run_id' => ['nullable', 'integer', 'exists:pilot_closure_runs,id'],
            'candidate_commit' => ['nullable', 'string', 'max:255'],
            'candidate_tag' => ['nullable', 'string', 'max:255'],
            'production_readiness_summary' => ['nullable', 'array'],
            'operator_handover_summary' => ['nullable', 'array'],
            'admin_handover_summary' => ['nullable', 'array'],
            'support_sla_summary' => ['nullable', 'array'],
            'backup_restore_summary' => ['nullable', 'array'],
            'ownership_matrix' => ['nullable', 'array'],
            'evidence_references' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
