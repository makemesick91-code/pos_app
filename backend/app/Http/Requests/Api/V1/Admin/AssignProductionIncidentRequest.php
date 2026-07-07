<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 19 — assign (or unassign) a production incident (platform admin).
 */
class AssignProductionIncidentRequest extends FormRequest
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
