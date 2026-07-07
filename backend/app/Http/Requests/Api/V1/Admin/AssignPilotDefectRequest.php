<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 17 — assign (or unassign) a pilot defect to a user.
 */
class AssignPilotDefectRequest extends FormRequest
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
            'assigned_to' => ['present', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
