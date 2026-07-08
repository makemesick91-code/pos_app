<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 23 — validates creation of a DRAFT SaaS billing invoice. Money totals are
 * NOT accepted here — they are recomputed server-side from invoice lines. The
 * invoice never triggers a payment gateway and never auto-suspends a tenant.
 */
class StoreBillingInvoiceRequest extends FormRequest
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
            'billing_account_id' => ['required', 'integer', 'exists:saas_billing_accounts,id'],
            'invoice_reference' => ['nullable', 'string', 'max:255', 'unique:saas_billing_invoices,invoice_reference'],
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:saas_billing_invoices,invoice_number'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_subscription_id' => ['nullable', 'integer', 'exists:tenant_subscriptions,id'],
            'billing_cycle_id' => ['nullable', 'integer', 'exists:saas_billing_cycles,id'],
            'currency' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
