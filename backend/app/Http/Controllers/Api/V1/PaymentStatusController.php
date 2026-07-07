<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QrisPaymentResource;
use App\Models\Payment;
use App\Support\TenantContext;

/**
 * Returns the current status of a tenant-owned payment (used by Android to poll
 * a QRIS payment). Cross-tenant payments 404. Never exposes provider secrets or
 * the raw gateway response.
 */
class PaymentStatusController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(Payment $payment): QrisPaymentResource
    {
        abort_unless(
            (int) $payment->tenant_id === (int) $this->context->tenantId(),
            404
        );

        return QrisPaymentResource::make($payment->loadMissing('sale'));
    }
}
