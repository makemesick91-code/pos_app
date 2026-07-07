<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalize an unpaid sale with CASH. The client supplies only paid_amount; the
 * amount charged, change, and PAID status are all recomputed by the backend.
 */
class StoreCashPaymentRequest extends FormRequest
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
        return [
            'amount' => ['prohibited'],
            'status' => ['prohibited'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
