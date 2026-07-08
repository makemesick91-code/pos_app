<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 23 — validates a complete/cancel transition of a collection activity.
 */
class TransitionBillingCollectionActivityRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
