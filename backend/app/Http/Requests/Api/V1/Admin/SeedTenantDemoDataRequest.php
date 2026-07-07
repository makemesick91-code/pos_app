<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 12 — validates a demo-data seed request for an existing tenant. The
 * store, when supplied, is verified to belong to the tenant in the controller.
 */
class SeedTenantDemoDataRequest extends FormRequest
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
            'store_id' => ['sometimes', 'nullable', 'integer'],
            'seed_products' => ['sometimes', 'boolean'],
            'seed_opening_inventory' => ['sometimes', 'boolean'],
            'seed_demo_sales' => ['sometimes', 'boolean'],
        ];
    }
}
