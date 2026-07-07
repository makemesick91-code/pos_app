<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared filters for the Sprint 9 report endpoints (daily sales, payment
 * summary, inventory movement summary, CSV export). All filters are optional and
 * scoped to the authenticated tenant; store_id/cashier_id must belong to the
 * tenant. When no date is provided the report services default to today.
 */
class ReportDateFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->hasTenant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'cashier_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /** Resolve the effective from/to window: explicit range, single date, else today. */
    public function dateFrom(): ?string
    {
        if ($this->filled('date_from')) {
            return $this->date('date_from')?->toDateString();
        }

        if ($this->filled('date')) {
            return $this->date('date')?->toDateString();
        }

        return null;
    }

    public function dateTo(): ?string
    {
        if ($this->filled('date_to')) {
            return $this->date('date_to')?->toDateString();
        }

        if ($this->filled('date')) {
            return $this->date('date')?->toDateString();
        }

        return null;
    }
}
