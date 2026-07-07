<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\Data\QrisCreateRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a backend-driven QRIS payment for a tenant-owned sale.
 *
 * Guarantees:
 *   - the sale must be neither PAID nor CANCELLED;
 *   - the amount is always the backend-computed grand_total (never client input);
 *   - a still-valid PENDING QRIS is reused instead of minting a duplicate QR;
 *   - the sale moves to PENDING while awaiting the gateway webhook.
 *
 * Provider credentials are resolved by QrisGatewayManager from config only.
 */
class QrisPaymentService
{
    public function __construct(private readonly QrisGatewayManager $manager) {}

    public function createForSale(Sale $sale, ?string $provider = null): Payment
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'sale' => 'A cancelled sale cannot be paid with QRIS.',
            ]);
        }

        if ($sale->isPaid()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale has already been paid.',
            ]);
        }

        // Reuse an existing, still-valid pending QRIS rather than duplicating it.
        $existing = $sale->payments()
            ->where('method', Payment::METHOD_QRIS)
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Throws PaymentGatewayException (→ 422) for disabled/unknown providers.
        $gateway = $this->manager->gateway($provider);

        $amount = bcadd((string) $sale->grand_total, '0', 2);

        $response = $gateway->create(new QrisCreateRequest(
            tenantId: (int) $sale->tenant_id,
            storeId: (int) $sale->store_id,
            saleId: (int) $sale->id,
            invoiceNumber: (string) $sale->invoice_number,
            amount: $amount,
            expiryMinutes: $this->manager->expiryMinutes(),
        ));

        return DB::transaction(function () use ($sale, $gateway, $response, $amount) {
            $payment = Payment::create([
                'tenant_id' => $sale->tenant_id,
                'store_id' => $sale->store_id,
                'sale_id' => $sale->id,
                'method' => Payment::METHOD_QRIS,
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
                'provider' => $gateway->name(),
                'provider_reference' => $response->providerReference,
                'qr_payload' => $response->qrPayload,
                'qr_image_url' => $response->qrImageUrl,
                'payment_url' => $response->paymentUrl,
                'metadata' => ['invoice_number' => $sale->invoice_number],
                'expired_at' => $response->expiredAt,
                'raw_response' => json_encode($response->rawResponse),
            ]);

            // Sale is awaiting settlement — only a paid QRIS flips it to PAID.
            if (! $sale->isPaid()) {
                $sale->update(['payment_status' => Sale::PAYMENT_STATUS_PENDING]);
            }

            return $payment;
        });
    }
}
