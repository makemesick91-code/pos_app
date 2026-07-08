<?php

namespace App\Services\ExportGovernance;

/**
 * Sprint 29 — read-only export governance coverage summary. Aggregates the
 * registry + server-side discovery into redacted counts and lists for the
 * platform-admin coverage endpoints and the coverage-summary command (EGC-R010/
 * R011). Never leaks secrets and never exposes cross-tenant data — it describes
 * routes, not tenant usage.
 */
class ExportGovernanceCoverageService
{
    public function __construct(
        private readonly ExportRouteRegistry $registry,
        private readonly ExportRouteDiscoveryService $discovery,
        private readonly ExportGovernanceAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $discovered = $this->discovery->discover();
        $metered = $this->registry->metered();
        $exempt = $this->registry->exempt();
        $gaps = $this->audit->gaps();

        return [
            'meter_key' => $this->registry->meterKey(),
            'event_key' => $this->registry->eventKey(),
            'event_category' => $this->registry->eventCategory(),
            'meterable' => (bool) ((array) config('tenant_plan.usage_limits', []))[$this->registry->meterKey()]['meterable'] ?? false,
            'totals' => [
                'discovered_export_like_routes' => count($discovered),
                'registered_routes' => count($this->registry->all()),
                'metered_routes' => count($metered),
                'exempt_routes' => count($exempt),
                'gaps' => count($gaps),
            ],
            'metered_routes' => $this->presentMetered($metered),
            'exempt_routes' => $this->presentExempt($exempt),
            'discovered_routes' => array_map(fn ($r) => [
                'signature' => $r['signature'],
                'uri' => $r['uri'],
                'registered' => $r['registered'],
                'disposition' => $r['disposition'],
            ], $discovered),
            'gaps' => $gaps,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $metered
     * @return array<int, array<string, mixed>>
     */
    private function presentMetered(array $metered): array
    {
        $out = [];
        foreach ($metered as $signature => $meta) {
            $out[] = [
                'signature' => $signature,
                'report_type' => $meta['report_type'] ?? null,
                'format' => $meta['format'] ?? null,
                'entitlement' => $meta['entitlement'] ?? null,
                'meter_key' => $meta['meter_key'] ?? null,
                'event_key' => $meta['event_key'] ?? null,
                'idempotency_strategy' => $meta['idempotency_strategy'] ?? null,
                'metadata_sanitized' => (bool) ($meta['metadata_sanitized'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $exempt
     * @return array<int, array<string, mixed>>
     */
    private function presentExempt(array $exempt): array
    {
        $out = [];
        foreach ($exempt as $signature => $meta) {
            $out[] = [
                'signature' => $signature,
                'report_type' => $meta['report_type'] ?? null,
                'format' => $meta['format'] ?? null,
                'exempt_reason' => $meta['exempt_reason'] ?? null,
            ];
        }

        return $out;
    }
}
