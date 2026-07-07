<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentStatusSynchronizer;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sprint 5 payment reconciliation foundation.
 *
 * Scans PENDING QRIS payments for a given date and expires any whose
 * `expired_at` has passed — driving the same idempotent state machine the
 * webhook uses (so the related sale is reconciled too). CASH payments are never
 * touched. No external gateway call is made here; live provider polling is a
 * later sprint.
 */
class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile {--date= : The sale/creation date to reconcile (YYYY-MM-DD), defaults to today}';

    protected $description = 'Reconcile PENDING QRIS payments for a date (expires those past their expiry).';

    public function handle(PaymentStatusSynchronizer $synchronizer): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : Carbon::today();

        $pending = Payment::query()
            ->where('method', Payment::METHOD_QRIS)
            ->where('status', Payment::STATUS_PENDING)
            ->whereDate('created_at', $date->toDateString())
            ->get();

        $checked = $pending->count();
        $expired = 0;

        foreach ($pending as $payment) {
            if ($payment->expired_at !== null && $payment->expired_at->isPast()) {
                if ($synchronizer->apply($payment, Payment::STATUS_EXPIRED)) {
                    $expired++;
                }
            }
        }

        $stillPending = $checked - $expired;

        $this->line("QRIS payments checked: {$checked}");
        $this->line("Expired locally: {$expired}");
        $this->line("Still pending: {$stillPending}");

        return self::SUCCESS;
    }
}
