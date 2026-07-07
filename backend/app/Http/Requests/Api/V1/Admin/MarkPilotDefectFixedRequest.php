<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 17 — mark a pilot defect as FIXED (not CLOSED). Optional evidence
 * reference. Verification/retest happens through the verify endpoint afterward.
 */
class MarkPilotDefectFixedRequest extends FormRequest
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
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
