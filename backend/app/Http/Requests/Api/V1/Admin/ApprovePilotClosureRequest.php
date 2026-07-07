<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 18 — validates approval of a pilot closure run (platform-admin only).
 */
class ApprovePilotClosureRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
