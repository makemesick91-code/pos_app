<?php

namespace App\Services\ExportGovernance;

/**
 * Sprint 29 — canonical read model over config('export_governance.routes'). It is
 * the single source of truth for which export-like routes are metered and which
 * are explicitly exempt (EGC-R001/R010). Used by the discovery scanner, the
 * enforcement audit, the go/no-go gate, the commands, and the admin coverage
 * endpoints so the registry is never docs-only.
 */
class ExportRouteRegistry
{
    public const DISPOSITION_METERED = 'metered';
    public const DISPOSITION_EXEMPT = 'exempt';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $routes = [];
        foreach ((array) config('export_governance.routes', []) as $signature => $meta) {
            $routes[(string) $signature] = (array) $meta;
        }

        return $routes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function metered(): array
    {
        return array_filter($this->all(), fn ($m) => ($m['disposition'] ?? null) === self::DISPOSITION_METERED);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function exempt(): array
    {
        return array_filter($this->all(), fn ($m) => ($m['disposition'] ?? null) === self::DISPOSITION_EXEMPT);
    }

    public function isRegistered(string $signature): bool
    {
        return array_key_exists($signature, $this->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $signature): ?array
    {
        return $this->all()[$signature] ?? null;
    }

    public function meterKey(): string
    {
        return (string) config('export_governance.meter_key', 'reports.exports.monthly');
    }

    public function eventKey(): string
    {
        return (string) config('export_governance.event_key', 'report.exported');
    }

    public function eventCategory(): string
    {
        return (string) config('export_governance.event_category', 'report_export');
    }
}
