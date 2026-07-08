<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 23 — validates issuing a DRAFT invoice. Issuing never triggers a payment
 * gateway and never auto-suspends a tenant.
 */
class IssueBillingInvoiceRequest extends FormRequest
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
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ];
    }
}
