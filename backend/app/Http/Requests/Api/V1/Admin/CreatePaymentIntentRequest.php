<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 31 — validate a payment intent creation request. The AMOUNT is never
 * accepted from the client — it always equals the invoice outstanding amount
 * (PGW-R005). Only provider/channel/metadata are inputs, each allowlisted.
 */
class CreatePaymentIntentRequest extends FormRequest
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
            'provider' => ['nullable', 'string', Rule::in(array_keys((array) config('payment_gateway_governance.providers', [])))],
            'channel' => ['nullable', 'string', Rule::in((array) config('payment_gateway_governance.channels', []))],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
