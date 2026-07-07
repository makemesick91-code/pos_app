<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Backend-only invoice number generator. The client never supplies or influences
 * the invoice number.
 *
 * Format: POS-{STORE_CODE}-{YYYYMMDD}-{000001}
 *
 * The sequence is scoped per (tenant_id, store_id, date) and derived from the
 * count of existing sales for that store/date, so different stores keep
 * independent sequences and numbers never collide across tenants. Uniqueness is
 * additionally guaranteed by the DB unique index on
 * (tenant_id, store_id, invoice_number); callers should generate inside the same
 * DB transaction that inserts the sale. Concurrency hardening (row locking /
 * dedicated sequence table) can be layered in later — sufficient for the MVP.
 */
class InvoiceNumberGenerator
{
    public function generate(int $tenantId, Store $store, ?Carbon $date = null): string
    {
        $date ??= now();
        $datePart = $date->format('Ymd');

        $countToday = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $store->id)
            ->whereDate('sale_date', $date->toDateString())
            ->count();

        $sequence = str_pad((string) ($countToday + 1), 6, '0', STR_PAD_LEFT);
        $storeCode = $this->normalizeStoreCode($store->code);

        return "POS-{$storeCode}-{$datePart}-{$sequence}";
    }

    private function normalizeStoreCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        return $code !== '' ? $code : 'STORE';
    }
}
