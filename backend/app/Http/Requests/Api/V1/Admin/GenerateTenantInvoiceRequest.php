<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 30 — validate an admin invoice generation request. Amounts are NEVER
 * accepted from the client (BIL-R003); only the period/source/reason are.
 */
class GenerateTenantInvoiceRequest extends FormRequest
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
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'source' => ['nullable', 'string', Rule::in((array) config('billing_governance.sources', []))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
