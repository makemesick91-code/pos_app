<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Single owner of QRIS payment status transitions and the resulting sale
 * `payment_status`. Centralising this keeps webhook processing and
 * reconciliation idempotent and consistent:
 *
 *   - PAID is terminal and is never downgraded (a late PENDING/EXPIRED is a no-op).
 *   - A repeated identical status is a no-op (webhook idempotency).
 *   - The sale only becomes EXPIRED/FAILED/CANCELLED when no paid payment exists.
 *
 * Returns true only when a real change was applied.
 */
class PaymentStatusSynchronizer
{
    private const SCALE = 2;

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function apply(
        Payment $payment,
        string $status,
        ?CarbonInterface $paidAt = null,
        array $rawResponse = [],
    ): bool {
        return DB::transaction(function () use ($payment, $status, $paidAt, $rawResponse) {
            /** @var Payment $fresh */
            $fresh = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            // Idempotency + no-downgrade guards.
            if ($fresh->status === $status) {
                return false;
            }

            if ($fresh->status === Payment::STATUS_PAID) {
                // PAID is terminal — never overwritten by a later callback.
                return false;
            }

            $fresh->status = $status;

            if ($status === Payment::STATUS_PAID) {
                $fresh->paid_at = $paidAt ?? now();
            }

            if (! empty($rawResponse)) {
                $fresh->raw_response = json_encode($rawResponse);
            }

            $fresh->save();

            $this->syncSale($fresh, $status);

            $payment->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    private function syncSale(Payment $payment, string $status): void
    {
        /** @var Sale|null $sale */
        $sale = Sale::query()->lockForUpdate()->find($payment->sale_id);

        if ($sale === null) {
            return;
        }

        // A sale that already has a paid payment is settled — protect it.
        $hasPaidPayment = $sale->payments()->where('status', Payment::STATUS_PAID)->exists();

        switch ($status) {
            case Payment::STATUS_PAID:
                $grandTotal = $this->normalize((string) $sale->grand_total);
                $sale->update([
                    'payment_status' => Sale::PAYMENT_STATUS_PAID,
                    'paid_total' => $grandTotal,
                    'change_total' => '0.00',
                ]);
                break;

            case Payment::STATUS_EXPIRED:
                if (! $hasPaidPayment && ! $sale->isCancelled()) {
                    $sale->update(['payment_status' => Sale::PAYMENT_STATUS_EXPIRED]);
                }
                break;

            case Payment::STATUS_FAILED:
                if (! $hasPaidPayment && ! $sale->isCancelled()) {
                    $sale->update(['payment_status' => Sale::PAYMENT_STATUS_FAILED]);
                }
                break;

            case Payment::STATUS_CANCELLED:
                if (! $hasPaidPayment && ! $sale->isCancelled()) {
                    $sale->update(['payment_status' => Sale::PAYMENT_STATUS_CANCELLED]);
                }
                break;
        }
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
