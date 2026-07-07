<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PilotClosureRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 18 — filters for listing pilot closure runs (platform-admin only; the
 * platform.admin middleware authorizes the route).
 */
class IndexPilotClosureRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(PilotClosureRun::STATUSES)],
            'decision' => ['nullable', Rule::in(PilotClosureRun::DECISIONS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
