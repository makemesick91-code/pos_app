<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 30 — a reason is mandatory for a manual payment mark-failed/cancel
 * mutation (BIL-R008). Shared by both endpoints.
 */
class PaymentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // platform.admin middleware authorizes.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
