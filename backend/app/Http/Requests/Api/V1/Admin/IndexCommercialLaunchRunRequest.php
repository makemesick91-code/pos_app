<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\CommercialLaunchRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 20 — validates filters for listing commercial launch runs.
 */
class IndexCommercialLaunchRunRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(CommercialLaunchRun::STATUSES)],
            'decision' => ['nullable', Rule::in(CommercialLaunchRun::DECISIONS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
