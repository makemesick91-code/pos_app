<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request a QRIS payment for a sale. The client may only choose a provider; the
 * amount, reference, QR payload and status are all backend/gateway-driven. An
 * absent provider falls back to config's default_qris_provider.
 */
class StoreQrisPaymentRequest extends FormRequest
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
            'provider' => ['sometimes', 'nullable', 'string', Rule::in(['fake', 'midtrans', 'xendit', 'duitku'])],
            'amount' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
