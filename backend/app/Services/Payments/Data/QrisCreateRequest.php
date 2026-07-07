<?php

namespace App\Services\Payments\Data;

/**
 * Immutable input for a QRIS payment creation. Built by QrisPaymentService from
 * a tenant-owned sale; amounts are always backend-computed (never client input).
 */
class QrisCreateRequest
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $storeId,
        public readonly int $saleId,
        public readonly string $invoiceNumber,
        public readonly string $amount,
        public readonly int $expiryMinutes,
    ) {}
}
