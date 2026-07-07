<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 17 — record a fix retest verification result. `passed` is explicit; on
 * PASS the defect becomes VERIFIED (and CLOSED when `close` is true), on FAIL it
 * returns to IN_PROGRESS.
 */
class VerifyPilotDefectRequest extends FormRequest
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
            'passed' => ['required', 'boolean'],
            'close' => ['sometimes', 'boolean'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
