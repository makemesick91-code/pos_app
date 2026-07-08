<?php

namespace App\Services\UsageEventLedger;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sprint 27 — closes the Sprint 26 deferred `reports.exports.monthly` meter with
 * real runtime metering (UEL-R006, UEL-R007).
 *
 * recordExport() is called by a report export controller ONLY after the export
 * has succeeded, so a failed/blocked export never counts (UEL-R008). Recording is
 * idempotent: an explicit `Idempotency-Key` request header is honoured, otherwise
 * a deterministic fingerprint is derived from tenant + route + user + report type
 * + normalized (sanitized) filters + a per-minute time bucket, so an accidental
 * retry of the same export within the same minute collapses to one event while
 * genuinely distinct exports each count (UEL-R004). currentMonthlyUsage() is the
 * canonical read used by the usage meter — it counts the ledger, never a stored
 * counter (UEL-R005, UEL-R006). Metadata holds only non-sensitive context.
 */
class ReportExportMeteringService
{
    use SanitizesUsageEventMetadata;

    /** Query params that must never enter a fingerprint or metadata verbatim. */
    private const SENSITIVE_FILTER_KEYS = [
        'password', 'secret', 'token', 'api_key', 'authorization', 'signature',
    ];

    public function __construct(
        private readonly UsageEventLedgerService $ledger,
    ) {}

    public function meterKey(): string
    {
        return TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY;
    }

    public function currentMonthlyUsage(Tenant $tenant): int
    {
        return $this->ledger->monthlyMeterCount($tenant, $this->meterKey());
    }

    /**
     * Record a successful report export as one usage event.
     *
     * @param array<string,mixed> $filters
     */
    public function recordExport(
        Tenant $tenant,
        ?User $actor,
        string $reportType,
        string $format,
        array $filters,
        Request $request,
        string $source = TenantUsageEvent::SOURCE_API,
    ): UsageEventDecision {
        $normalizedFilters = $this->normalizeFilters($filters);
        $idempotencyKey = $this->resolveIdempotencyKey($request, $tenant, $actor, $reportType, $normalizedFilters);

        return $this->ledger->append(
            tenant: $tenant,
            eventKey: TenantUsageEvent::EVENT_REPORT_EXPORTED,
            eventCategory: TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            meterKey: $this->meterKey(),
            idempotencyKey: $idempotencyKey,
            period: 'monthly',
            quantity: 1,
            source: $source,
            actorType: $actor !== null ? User::class : null,
            actorId: $actor?->id,
            subjectType: null,
            subjectId: null,
            requestFingerprint: $this->fingerprint($tenant, $actor, $reportType, $normalizedFilters),
            metadata: [
                'report_type' => $reportType,
                'format' => $format,
                'route' => (string) ($request->route()?->uri() ?? $request->path()),
                'filters' => $normalizedFilters,
            ],
        );
    }

    /**
     * @param array<string,mixed> $normalizedFilters
     */
    private function resolveIdempotencyKey(
        Request $request,
        Tenant $tenant,
        ?User $actor,
        string $reportType,
        array $normalizedFilters,
    ): string {
        $header = trim((string) $request->header('Idempotency-Key', ''));
        if ($header !== '') {
            // Namespace the client key by tenant so keys can never collide across
            // tenants and can never bypass the per-tenant unique constraint.
            return 'hdr:'.$tenant->id.':'.substr(hash('sha256', $header), 0, 48);
        }

        $bucket = now()->format('YmdHi'); // per-minute bucket for retry collapse

        return 'fp:'.$this->fingerprint($tenant, $actor, $reportType, $normalizedFilters).':'.$bucket;
    }

    /**
     * @param array<string,mixed> $normalizedFilters
     */
    private function fingerprint(Tenant $tenant, ?User $actor, string $reportType, array $normalizedFilters): string
    {
        $material = implode('|', [
            'tenant:'.$tenant->id,
            'user:'.($actor?->id ?? 'none'),
            'report:'.$reportType,
            'filters:'.json_encode($normalizedFilters),
        ]);

        return substr(hash('sha256', $material), 0, 48);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array(strtolower((string) $key), self::SENSITIVE_FILTER_KEYS, true)) {
                continue;
            }
            $clean[(string) $key] = is_scalar($value) ? $value : json_encode($value);
        }
        ksort($clean);

        return (array) $this->sanitizeMetadata($clean);
    }
}
