<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\Sale;
use App\Services\ReceiptService;
use App\Support\TenantContext;

/**
 * Tenant-isolated receipt preview. Ownership is enforced with a 404 (like the
 * sales API) so tenant A can never learn that tenant B's sale exists, let alone
 * read/print its receipt. A not-printable sale still returns HTTP 200 with
 * printable=false and a reason — the client decides whether to allow printing.
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ReceiptService $receipts,
    ) {}

    public function show(Sale $sale): ReceiptResource
    {
        abort_unless(
            (int) $sale->tenant_id === (int) $this->context->tenantId(),
            404
        );

        return ReceiptResource::make($this->receipts->build($sale));
    }
}
