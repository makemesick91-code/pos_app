<?php

namespace App\Http\Requests\Api\V1\Android;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 34 — validates an inbound Android sync batch. Every item MUST carry a
 * client_item_id (ADR-R012). The batch client id / idempotency key drive server
 * side duplicate protection (ADR-R013/R014).
 */
class SyncBatchRequest extends FormRequest
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
            'client_batch_id' => ['required', 'string', 'min:8', 'max:191'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:191'],
            'register_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.client_item_id' => ['required', 'string', 'min:1', 'max:191'],
            'items.*.item_type' => ['required', 'string', 'max:40'],
            'items.*.action' => ['nullable', 'string', 'max:40'],
            'items.*.payload' => ['nullable', 'array'],
        ];
    }
}
