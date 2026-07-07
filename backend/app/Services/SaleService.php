<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the money-critical sale lifecycle: creating a sale from a cart, finalizing
 * a CASH payment, and cancelling a sale.
 *
 * Two invariants the whole sprint hangs on:
 *   1. All totals are recomputed here from tenant-owned data — client totals are
 *      never read.
 *   2. product_name and unit_price are snapshotted into sale_items so historical
 *      transactions are immutable against later catalog edits.
 *
 * Money is handled with bcmath at scale 2 to avoid float drift.
 */
class SaleService
{
    private const SCALE = 2;

    public function __construct(
        private readonly InvoiceNumberGenerator $invoiceNumbers,
        private readonly ProductPriceResolver $priceResolver,
    ) {}

    /**
     * Create a sale (with an inline CASH payment) from a validated request.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCashSale(TenantContext $context, array $data): Sale
    {
        $tenant = $context->tenant();
        $store = $this->requireStore($context);
        $cashier = $context->user();

        $clientReference = $this->normalizeReference($data['client_reference'] ?? null);
        $source = $this->resolveSource($data['source'] ?? null);

        // Sprint 7 idempotency: a retried offline submit carrying a reference we
        // have already stored must return the original sale, never a duplicate.
        if ($clientReference !== null) {
            $existing = $this->findByClientReference($tenant->id, $store->id, $clientReference);

            if ($existing !== null) {
                $existing->idempotentReplay = true;

                return $existing->load(['items', 'payments']);
            }
        }

        $lines = $this->buildLines($tenant->id, $store->id, $data['items']);

        $subtotal = '0.00';
        $discountTotal = '0.00';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['gross'], self::SCALE);
            $discountTotal = bcadd($discountTotal, $line['discount'], self::SCALE);
        }

        $grandTotal = bcsub($subtotal, $discountTotal, self::SCALE);
        if (bccomp($grandTotal, '0.00', self::SCALE) < 0) {
            $grandTotal = '0.00';
        }

        $paidAmount = $this->normalize((string) $data['payment']['paid_amount']);
        if (bccomp($paidAmount, $grandTotal, self::SCALE) < 0) {
            throw ValidationException::withMessages([
                'payment.paid_amount' => 'Paid amount must be greater than or equal to the grand total.',
            ]);
        }

        $changeTotal = bcsub($paidAmount, $grandTotal, self::SCALE);

        $isOffline = $source === Sale::SOURCE_ANDROID_OFFLINE;
        $syncedAt = ($clientReference !== null || $isOffline) ? now() : null;
        $clientCreatedAt = isset($data['client_created_at'])
            ? Carbon::parse($data['client_created_at'])
            : null;

        try {
            return DB::transaction(function () use (
                $tenant,
                $store,
                $cashier,
                $lines,
                $subtotal,
                $discountTotal,
                $grandTotal,
                $paidAmount,
                $changeTotal,
                $source,
                $clientReference,
                $clientCreatedAt,
                $syncedAt,
                $data
            ) {
                $sale = Sale::create([
                    'tenant_id' => $tenant->id,
                    'store_id' => $store->id,
                    'device_id' => null,
                    'cashier_id' => $cashier->id,
                    'invoice_number' => $this->invoiceNumbers->generate($tenant->id, $store),
                    'sale_date' => now(),
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'tax_total' => '0.00',
                    'grand_total' => $grandTotal,
                    'paid_total' => $paidAmount,
                    'change_total' => $changeTotal,
                    'payment_status' => Sale::PAYMENT_STATUS_PAID,
                    'sync_status' => Sale::SYNC_STATUS_SYNCED,
                    'source' => $source,
                    'client_reference' => $clientReference,
                    'client_created_at' => $clientCreatedAt,
                    'synced_at' => $syncedAt,
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->persistLines($sale, $lines);

                Payment::create([
                    'tenant_id' => $tenant->id,
                    'store_id' => $store->id,
                    'sale_id' => $sale->id,
                    'method' => Payment::METHOD_CASH,
                    'amount' => $paidAmount,
                    'status' => Payment::STATUS_PAID,
                    'provider' => Payment::PROVIDER_MANUAL,
                    'paid_at' => now(),
                ]);

                return $sale->load(['items', 'payments']);
            });
        } catch (QueryException $e) {
            // Lost a race with a concurrent replay of the same offline reference:
            // fall back to the sale the winning request created.
            if ($clientReference !== null) {
                $existing = $this->findByClientReference($tenant->id, $store->id, $clientReference);

                if ($existing !== null) {
                    $existing->idempotentReplay = true;

                    return $existing->load(['items', 'payments']);
                }
            }

            throw $e;
        }
    }

    private function findByClientReference(int $tenantId, int $storeId, string $clientReference): ?Sale
    {
        return Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('client_reference', $clientReference)
            ->first();
    }

    private function normalizeReference(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function resolveSource(?string $source): string
    {
        $allowed = [
            Sale::SOURCE_ANDROID_ONLINE,
            Sale::SOURCE_ANDROID_OFFLINE,
            Sale::SOURCE_WEB_ADMIN,
            Sale::SOURCE_API,
        ];

        return in_array($source, $allowed, true) ? $source : Sale::SOURCE_ANDROID_ONLINE;
    }

    /**
     * Finalize an existing UNPAID sale with CASH.
     */
    public function payCash(Sale $sale, float|string $paidAmount): Sale
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'sale' => 'A cancelled sale cannot be paid.',
            ]);
        }

        if ($sale->isPaid()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale has already been paid.',
            ]);
        }

        $paid = $this->normalize((string) $paidAmount);
        $grandTotal = $this->normalize((string) $sale->grand_total);

        if (bccomp($paid, $grandTotal, self::SCALE) < 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid amount must be greater than or equal to the grand total.',
            ]);
        }

        $change = bcsub($paid, $grandTotal, self::SCALE);

        return DB::transaction(function () use ($sale, $paid, $change) {
            Payment::create([
                'tenant_id' => $sale->tenant_id,
                'store_id' => $sale->store_id,
                'sale_id' => $sale->id,
                'method' => Payment::METHOD_CASH,
                'amount' => $paid,
                'status' => Payment::STATUS_PAID,
                'provider' => Payment::PROVIDER_MANUAL,
                'paid_at' => now(),
            ]);

            $sale->update([
                'paid_total' => $paid,
                'change_total' => $change,
                'payment_status' => Sale::PAYMENT_STATUS_PAID,
            ]);

            return $sale->load(['items', 'payments']);
        });
    }

    public function cancel(Sale $sale, User $user): Sale
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale is already cancelled.',
            ]);
        }

        $sale->update([
            'payment_status' => Sale::PAYMENT_STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        return $sale->load(['items', 'payments']);
    }

    private function requireStore(TenantContext $context): Store
    {
        $store = $context->store();

        if ($store === null) {
            throw ValidationException::withMessages([
                'store_id' => 'A store context is required to create a sale. Assign the cashier to a store or send a valid X-Store-ID.',
            ]);
        }

        return $store;
    }

    /**
     * Resolve, validate, and snapshot each cart line. Product ownership is already
     * enforced by the request rules; here we re-check active state and resolve the
     * effective price.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(int $tenantId, int $storeId, array $items): array
    {
        $lines = [];

        foreach ($items as $index => $item) {
            /** @var Product $product */
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($item['product_id'])
                ->first();

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'The selected product is unavailable for this tenant.',
                ]);
            }

            $qty = $this->normalize((string) $item['qty']);
            $unitPrice = $this->normalize($this->priceResolver->resolve($product, $storeId));
            $discount = $this->normalize((string) ($item['discount'] ?? '0'));

            $gross = bcmul($qty, $unitPrice, self::SCALE);
            $subtotal = bcsub($gross, $discount, self::SCALE);
            if (bccomp($subtotal, '0.00', self::SCALE) < 0) {
                $subtotal = '0.00';
            }

            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'gross' => $gross,
                'subtotal' => $subtotal,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function persistLines(Sale $sale, array $lines): void
    {
        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            $sale->items()->create([
                'tenant_id' => $sale->tenant_id,
                'store_id' => $sale->store_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_barcode' => $product->barcode,
                'unit' => $product->unit,
                'qty' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'],
                'subtotal' => $line['subtotal'],
            ]);
        }
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
