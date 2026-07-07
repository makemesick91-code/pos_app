<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single (method, status) payment summary row (Sprint 9). Only aggregate
 * counts/amounts are exposed — never a raw gateway payload or secret.
 *
 * @property array<string, mixed> $resource
 */
class PaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->resource['method'],
            'status' => $this->resource['status'],
            'count' => $this->resource['count'],
            'amount_total' => $this->resource['amount_total'],
        ];
    }
}
