<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Sale;

/**
 * Builds a print-ready receipt payload from a tenant-owned sale.
 *
 * Two invariants Sprint 6 hangs on:
 *   1. Every line comes from the immutable sale_item snapshot (product_name,
 *      product_sku, unit, qty, unit_price, discount, subtotal) — never from the
 *      live product catalog, so a later price/name edit can never rewrite a
 *      historical receipt.
 *   2. A receipt is only FINAL/printable when the sale is actually PAID. Unpaid,
 *      pending (QRIS not settled), failed, expired and cancelled sales never
 *      yield a final printable receipt; they return printable=false with a
 *      human-readable reason instead of throwing.
 *
 * Payment secrets never leave the backend: only method/provider/status/amount/
 * paid_at are exposed. The Payment model's `raw_response` is hidden and is never
 * read here.
 */
class ReceiptService
{
    public const STATUS_FINAL = 'FINAL';
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_NOT_PRINTABLE = 'NOT_PRINTABLE';

    /**
     * @return array<string, mixed>
     */
    public function build(Sale $sale): array
    {
        $sale->loadMissing(['items', 'payments', 'store', 'cashier']);

        [$receiptStatus, $printable, $blockReason] = $this->resolveEligibility($sale);

        return [
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'receipt_status' => $receiptStatus,
            'printable' => $printable,
            'print_block_reason' => $blockReason,
            'store' => [
                'name' => $sale->store?->name,
                'code' => $sale->store?->code,
                'address' => $sale->store?->address,
            ],
            'cashier' => [
                'name' => $sale->cashier?->name,
            ],
            'sale_date' => optional($sale->sale_date)->toIso8601String(),
            'payment_status' => $sale->payment_status,
            'items' => $sale->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'subtotal' => $item->subtotal,
            ])->all(),
            'payments' => $sale->payments->map(fn (Payment $payment) => [
                'method' => $payment->method,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => optional($payment->paid_at)->toIso8601String(),
            ])->all(),
            'totals' => [
                'subtotal' => $sale->subtotal,
                'discount_total' => $sale->discount_total,
                'tax_total' => $sale->tax_total,
                'grand_total' => $sale->grand_total,
                'paid_total' => $sale->paid_total,
                'change_total' => $sale->change_total,
            ],
            'footer' => 'Terima kasih',
        ];
    }

    /**
     * Decide whether a receipt is final/printable from the authoritative sale
     * payment status only. QRIS settlement is already reflected on the sale by
     * the Sprint 5 status synchronizer, so PAID here covers both CASH and QRIS.
     *
     * @return array{0: string, 1: bool, 2: string|null}
     */
    private function resolveEligibility(Sale $sale): array
    {
        return match ($sale->payment_status) {
            Sale::PAYMENT_STATUS_PAID => [self::STATUS_FINAL, true, null],
            Sale::PAYMENT_STATUS_PENDING => [
                self::STATUS_DRAFT,
                false,
                'Pembayaran QRIS masih menunggu konfirmasi. Struk final belum dapat dicetak.',
            ],
            Sale::PAYMENT_STATUS_UNPAID => [
                self::STATUS_NOT_PRINTABLE,
                false,
                'Penjualan belum dibayar.',
            ],
            Sale::PAYMENT_STATUS_CANCELLED => [
                self::STATUS_NOT_PRINTABLE,
                false,
                'Penjualan dibatalkan.',
            ],
            Sale::PAYMENT_STATUS_EXPIRED => [
                self::STATUS_NOT_PRINTABLE,
                false,
                'Pembayaran kedaluwarsa.',
            ],
            Sale::PAYMENT_STATUS_FAILED => [
                self::STATUS_NOT_PRINTABLE,
                false,
                'Pembayaran gagal.',
            ],
            default => [
                self::STATUS_NOT_PRINTABLE,
                false,
                'Penjualan tidak memenuhi syarat cetak struk.',
            ],
        };
    }
}
