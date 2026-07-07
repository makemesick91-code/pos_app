<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PilotDefect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 17 — transition a pilot defect to a new status. Accept-risk, mark-fixed,
 * and verify have dedicated endpoints; this covers generic transitions
 * (OPEN/IN_PROGRESS/RETEST/CLOSED …).
 */
class TransitionPilotDefectStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(PilotDefect::STATUSES)],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
