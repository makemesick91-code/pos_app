<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 22 — validates creating a sales lead activity. WHATSAPP_MANUAL and
 * EMAIL_MANUAL are manual notes only — no real message is ever sent.
 */
class StoreSalesLeadActivityRequest extends FormRequest
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
            'activity_type' => ['required', Rule::in(SalesLeadActivity::TYPES)],
            'status' => ['nullable', Rule::in(SalesLeadActivity::STATUSES)],
            'summary' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
