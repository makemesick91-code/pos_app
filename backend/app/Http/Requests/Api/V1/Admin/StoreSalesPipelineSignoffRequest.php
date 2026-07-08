<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesPipelineSignoff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates recording a sales pipeline sign-off. A REJECTED signoff
 * forces NO-GO; APPROVED_WITH_RISK forces WATCH.
 */
class StoreSalesPipelineSignoffRequest extends FormRequest
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
            'signoff_reference' => ['nullable', 'string', 'max:255', 'unique:sales_pipeline_signoffs,signoff_reference'],
            'signer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_role' => ['required', Rule::in(SalesPipelineSignoff::ROLES)],
            'decision' => ['required', Rule::in(SalesPipelineSignoff::DECISIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
