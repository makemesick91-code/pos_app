<?php

namespace App\Services\BillingConsole;

use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Services\Billing\BillingSummaryService;
use App\Services\OwnerConsole\OwnerContext;
use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UIX-5 — assembles read-only view models for the Subscription/Billing/Invoice
 * console on BOTH the Tenant Owner (`/owner/billing/*`) and Platform Admin
 * (`/admin/billing/*`) surfaces.
 *
 * This is a presentation/read adapter, NOT a second billing engine (UIX5-R001/
 * R002). It never recomputes an invoice total, tax, discount, paid, or
 * outstanding value: it reads the canonical persisted columns and calls the
 * canonical domain methods/services — {@see BillingSummaryService} (which itself
 * uses {@see TenantBillingInvoice::collectedAmount()}/`outstandingAmount()`),
 * {@see PaymentGatewaySummaryService}, {@see TenantPlanResolver}. Money is always
 * a whole-rupiah integer (never a float, never divided by 100 — UIX5-R008).
 *
 * Tenant scope is deny-by-default: every owner/tenant read passes a non-null
 * `$tenantId` and is constrained to it (UIX5-R003/R006). A null `$tenantId` is a
 * deliberate platform-wide read, reachable ONLY on the platform-admin surface.
 * A failed downstream read degrades to a truthful `['available' => false]`
 * rather than a fabricated zero (UIX5-R013).
 */
class BillingConsoleReadService
{
    /** Whitelisted invoice sort columns (UIX5-R022). */
    private const INVOICE_SORTS = ['issued_at', 'due_at', 'total_amount', 'id'];

    /** Max recent invoices shown on an overview panel. */
    private const RECENT_LIMIT = 5;

    public function __construct(
        private readonly BillingSummaryService $billingSummary,
        private readonly PaymentGatewaySummaryService $gateway,
        private readonly TenantPlanResolver $plans,
    ) {}

    /**
     * Owner Billing Center overview — tenant-scoped to the owner's own tenant.
     *
     * @return array<string, mixed>
     */
    public function ownerOverview(OwnerContext $context): array
    {
        $tenantId = $context->tenantId();

        return [
            'lifecycle' => $context->lifecycle,
            'plan' => $this->safe(fn () => $this->planSummary($context)),
            'invoices' => $this->safe(fn () => $this->billingSummary->invoiceSummary($tenantId)),
            'collection' => $this->safe(fn () => $this->billingSummary->collectionSummary($tenantId)),
            'settlement' => $this->safe(fn () => $this->gateway->settlementSummary($tenantId)),
            'recent' => $this->safe(fn () => [
                'items' => $this->recentInvoices($tenantId),
            ]),
        ];
    }

    /**
     * Platform Admin Billing Operations overview — platform-wide aggregates via
     * the canonical summary services (no per-tenant recomputation).
     *
     * @return array<string, mixed>
     */
    public function adminOverview(): array
    {
        return [
            'invoices' => $this->safe(fn () => $this->billingSummary->invoiceSummary(null)),
            'collection' => $this->safe(fn () => $this->billingSummary->collectionSummary(null)),
            'settlement' => $this->safe(fn () => $this->gateway->settlementSummary(null)),
            'intents' => $this->safe(fn () => $this->gateway->intentSummary(null)),
            'recent' => $this->safe(fn () => [
                'items' => $this->recentInvoices(null),
            ]),
        ];
    }

    /**
     * Per-tenant billing panel for the admin tenant-detail surface
     * (`/admin/tenants/{tenant}/billing`). Scoped to the given tenant.
     *
     * @return array<string, mixed>
     */
    public function adminTenantBilling(int $tenantId): array
    {
        return [
            'invoices' => $this->safe(fn () => $this->billingSummary->invoiceSummary($tenantId)),
            'collection' => $this->safe(fn () => $this->billingSummary->collectionSummary($tenantId)),
            'settlement' => $this->safe(fn () => $this->gateway->settlementSummary($tenantId)),
            'recent' => $this->safe(fn () => [
                'items' => $this->recentInvoices($tenantId),
            ]),
        ];
    }

    /**
     * Paginated invoice list. When `$tenantId` is non-null the query is
     * constrained to that tenant (owner surface, and the admin per-tenant view);
     * a null `$tenantId` is a platform-wide admin list. Filters and sorting are
     * strictly whitelisted (UIX5-R021/R022). `collected_amount` is eager-summed
     * with the SAME recorded+confirmed rule as
     * {@see TenantBillingInvoice::collectedAmount()} to avoid an N+1 while keeping
     * the canonical definition.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<TenantBillingInvoice>
     */
    public function paginateInvoices(?int $tenantId, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::INVOICE_SORTS, true) ? $filters['sort'] : 'issued_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(10, min((int) ($filters['per_page'] ?? 20), 50));

        $query = TenantBillingInvoice::query()
            ->withSum(['payments as collected_amount' => function (Builder $q): void {
                $q->whereIn('status', [TenantBillingPayment::STATUS_RECORDED, TenantBillingPayment::STATUS_CONFIRMED]);
            }], 'amount');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, $this->invoiceStatuses(), true)) {
            $query->where('status', $status);
        }

        $collection = (string) ($filters['collection'] ?? '');
        if (in_array($collection, $this->collectionStates(), true)) {
            $query->where('collection_state', $collection);
        }

        $period = (string) ($filters['period'] ?? '');
        if ($period !== '' && preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            $query->where('period_key', $period);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('invoice_number', 'like', $term);
        }

        return $query->orderBy($sort, $direction)->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
    }

    /**
     * Resolve a single invoice within scope, or null when it does not belong to
     * the scope (owner: the owner's tenant). The caller renders 404 — this is the
     * deny-by-default IDOR guard (UIX5-R006). NEVER use implicit route-model
     * binding for owner invoices.
     */
    public function findInvoice(?int $tenantId, int $invoiceId): ?TenantBillingInvoice
    {
        return TenantBillingInvoice::query()
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->find($invoiceId);
    }

    /**
     * Full invoice detail view model: canonical header/amounts + scoped payments,
     * payment intents (QRIS lifecycle), and gateway/settlement events. All values
     * are read from canonical columns/methods; sensitive material (metadata,
     * signature/payload hashes) is never included (UIX5-R019).
     *
     * @return array<string, mixed>
     */
    public function invoiceDetail(TenantBillingInvoice $invoice): array
    {
        return [
            'invoice' => $this->presentInvoice($invoice),
            'payments' => $this->presentPayments($invoice),
            'intents' => $this->presentIntents($invoice),
        ];
    }

    /**
     * Print-safe, authenticated invoice document view model (UIX5-R018). Same
     * canonical data as the detail view plus the tenant billing identity. There
     * is no separate rendered PDF generator in this codebase, so the document is
     * delivered as authenticated print-ready HTML, never a public file URL.
     *
     * @return array<string, mixed>
     */
    public function invoiceDocument(TenantBillingInvoice $invoice): array
    {
        $invoice->loadMissing('tenant');

        return [
            'invoice' => $this->presentInvoice($invoice),
            'payments' => $this->presentPayments($invoice),
            'tenant' => [
                'name' => $invoice->tenant?->name,
                'code' => $invoice->tenant?->code,
            ],
        ];
    }

    /**
     * Display-ready row/header for one invoice, from canonical columns + methods.
     *
     * @return array<string, mixed>
     */
    public function presentInvoice(TenantBillingInvoice $invoice): array
    {
        // `collected_amount` is populated by withSum on the list query; on a
        // single loaded invoice fall back to the canonical model method.
        $collected = $invoice->collected_amount !== null
            ? (int) $invoice->collected_amount
            : $invoice->collectedAmount();
        $outstanding = max(0, (int) $invoice->total_amount - $collected);

        return [
            'id' => (int) $invoice->id,
            'tenant_id' => (int) $invoice->tenant_id,
            'invoice_number' => $invoice->invoice_number,
            'plan_key' => $invoice->plan_key,
            'period_key' => $invoice->period_key,
            'period_start' => $invoice->period_start,
            'period_end' => $invoice->period_end,
            'issued_at' => $invoice->issued_at,
            'due_at' => $invoice->due_at,
            'currency' => $invoice->currency,
            'subtotal_amount' => (int) $invoice->subtotal_amount,
            'discount_amount' => (int) $invoice->discount_amount,
            'tax_amount' => (int) $invoice->tax_amount,
            'total_amount' => (int) $invoice->total_amount,
            'collected_amount' => $collected,
            'outstanding_amount' => $outstanding,
            'status' => $invoice->status,
            'collection_state' => $invoice->collection_state,
            'is_paid' => $invoice->isPaid(),
            'source' => $invoice->source,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presentPayments(TenantBillingInvoice $invoice): array
    {
        return $invoice->payments()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (TenantBillingPayment $p): array => [
                'payment_reference' => $p->payment_reference,
                'method' => $p->method,
                'status' => $p->status,
                'collection_state' => $p->collection_state,
                'amount' => (int) $p->amount,
                'currency' => $p->currency,
                'counts' => $p->counts(),
                'received_at' => $p->received_at,
            ])
            ->all();
    }

    /**
     * QRIS / gateway payment intents for this invoice, each with its normalized
     * gateway events. Truthful lifecycle: an intent that merely exists is NOT
     * "settled" — its own status is shown verbatim (UIX5-R011/R012). No signature
     * or payload hash is exposed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentIntents(TenantBillingInvoice $invoice): array
    {
        return TenantBillingPaymentIntent::query()
            ->where('invoice_id', $invoice->id)
            ->with(['events' => fn ($q) => $q->orderByDesc('id')->limit(20)])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (TenantBillingPaymentIntent $intent): array => [
                'provider' => $intent->provider,
                'channel' => $intent->channel,
                'status' => $intent->status,
                'is_open' => $intent->isOpen(),
                'is_paid' => $intent->isPaid(),
                'amount' => (int) $intent->amount,
                'currency' => $intent->currency,
                'provider_reference' => $intent->provider_reference,
                'expires_at' => $intent->expires_at,
                'paid_at' => $intent->paid_at,
                'events' => $intent->events->map(fn ($e): array => [
                    'event_type' => $e->event_type,
                    'normalized_status' => $e->normalized_status,
                    'signature_verified' => (bool) $e->signature_verified,
                    'occurred_at' => $e->occurred_at,
                ])->all(),
            ])
            ->all();
    }

    /**
     * A small, bounded recent-invoice list for overview panels.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentInvoices(?int $tenantId): array
    {
        return TenantBillingInvoice::query()
            ->withSum(['payments as collected_amount' => function (Builder $q): void {
                $q->whereIn('status', [TenantBillingPayment::STATUS_RECORDED, TenantBillingPayment::STATUS_CONFIRMED]);
            }], 'amount')
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (TenantBillingInvoice $i) => $this->presentInvoice($i))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function planSummary(OwnerContext $context): array
    {
        $decision = $this->plans->resolve($context->tenant);

        return [
            'plan_key' => $decision->planKey,
            'plan_name' => $decision->planName,
            'has_explicit_assignment' => $decision->hasExplicitAssignment,
        ];
    }

    /** @return array<int, string> */
    public function invoiceStatuses(): array
    {
        return [
            TenantBillingInvoice::STATUS_DRAFT,
            TenantBillingInvoice::STATUS_ISSUED,
            TenantBillingInvoice::STATUS_VOID,
            TenantBillingInvoice::STATUS_CANCELLED,
        ];
    }

    /** @return array<int, string> */
    public function collectionStates(): array
    {
        return [
            TenantBillingInvoice::COLLECTION_NOT_DUE,
            TenantBillingInvoice::COLLECTION_PENDING,
            TenantBillingInvoice::COLLECTION_PAID,
            TenantBillingInvoice::COLLECTION_FAILED,
            TenantBillingInvoice::COLLECTION_OVERDUE,
            TenantBillingInvoice::COLLECTION_WRITTEN_OFF,
            TenantBillingInvoice::COLLECTION_CANCELLED,
        ];
    }

    /**
     * Run a read and normalise it into an availability-tagged panel. A failed
     * downstream read never leaks as a fabricated zero (UIX5-R013).
     *
     * @param  callable(): mixed  $read
     * @return array<string, mixed>
     */
    private function safe(callable $read): array
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            Log::warning('billing.console.panel_unavailable', ['exception' => $e::class]);

            return ['available' => false];
        }

        if (is_array($value)) {
            return ['available' => true] + $value;
        }

        return ['available' => true, 'value' => $value];
    }
}
