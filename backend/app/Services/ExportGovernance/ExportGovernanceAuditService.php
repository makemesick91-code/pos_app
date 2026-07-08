<?php

namespace App\Services\ExportGovernance;

use App\Http\Middleware\EnsureTenantUsageLimitAvailable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Sprint 29 — audits that export governance coverage is actually enforced, not
 * just declared. Fails (NO_GO) when: an export-like route is discovered but not
 * registered (EGC-R001); a metered route is missing the lifecycle → entitlement →
 * usage guards in that order (EGC-R003/R004); a metered route uses a non-canonical
 * meter/event key (EGC-R005/R006); idempotency strategy or metadata sanitizer is
 * missing (EGC-R008/R009); an exemption carries no reason (EGC-R010); the
 * reports.exports.monthly meter is not meterable (EGC-R013); or a hard guardrail
 * flag is enabled. Read-only — never mutates a route, ledger, or tenant.
 */
class ExportGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly Router $router,
        private readonly ExportRouteRegistry $registry,
        private readonly ExportRouteDiscoveryService $discovery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->aliasSignal(),
            $this->discoveryCoverageSignal(),
            $this->meteredGuardSignal(),
            $this->exemptionReasonSignal(),
            $this->meterableSignal(),
            $this->guardrailSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gaps' => $this->gaps(),
        ];
    }

    /**
     * All critical governance gaps as human-readable strings.
     *
     * @return array<int, string>
     */
    public function gaps(): array
    {
        return array_merge(
            $this->unregisteredGaps(),
            $this->meteredGuardGaps(),
            $this->exemptionGaps(),
        );
    }

    private function aliasSignal(): array
    {
        $aliases = $this->router->getMiddleware();

        return ($aliases['tenant.usage.limit'] ?? null) === EnsureTenantUsageLimitAvailable::class
            ? $this->signal('usage_guard_alias', self::STATUS_PASS, 'tenant.usage.limit alias registered.')
            : $this->signal('usage_guard_alias', self::STATUS_FAIL, 'tenant.usage.limit middleware alias missing.');
    }

    private function discoveryCoverageSignal(): array
    {
        $gaps = $this->unregisteredGaps();

        return $gaps === []
            ? $this->signal('export_route_coverage', self::STATUS_PASS, 'All discovered export-like routes are registered (EGC-R001/R002).')
            : $this->signal('export_route_coverage', self::STATUS_FAIL, count($gaps).' unregistered export-like route(s): '.implode('; ', $gaps));
    }

    private function meteredGuardSignal(): array
    {
        $gaps = $this->meteredGuardGaps();

        return $gaps === []
            ? $this->signal('metered_guard_coverage', self::STATUS_PASS, 'All metered export routes carry lifecycle → entitlement → usage guards + canonical taxonomy + idempotency + sanitizer (EGC-R003..R009).')
            : $this->signal('metered_guard_coverage', self::STATUS_FAIL, count($gaps).' metered export route gap(s): '.implode('; ', $gaps));
    }

    private function exemptionReasonSignal(): array
    {
        $gaps = $this->exemptionGaps();

        return $gaps === []
            ? $this->signal('exemption_reasons', self::STATUS_PASS, 'All export exemptions carry an explicit reason (EGC-R010).')
            : $this->signal('exemption_reasons', self::STATUS_FAIL, count($gaps).' exemption gap(s): '.implode('; ', $gaps));
    }

    private function meterableSignal(): array
    {
        $meterKey = $this->registry->meterKey();
        // Literal (dotted) array key — read the array, not a config dot-path.
        $limits = (array) config('tenant_plan.usage_limits', []);

        return (bool) ($limits[$meterKey]['meterable'] ?? false)
            ? $this->signal('meter_live', self::STATUS_PASS, $meterKey.' remains meterable from the ledger (EGC-R013).')
            : $this->signal('meter_live', self::STATUS_FAIL, $meterKey.' is not meterable — export metering is not live.');
    }

    private function guardrailSignal(): array
    {
        $flags = [
            'export_metering_bypass_route_allowed',
            'unregistered_export_route_allowed',
            'export_exemption_without_reason_allowed',
            'client_side_export_authoritative',
            'blocked_export_counts_usage_allowed',
        ];
        $enabled = array_values(array_filter($flags, fn ($f) => config('export_governance.'.$f) === true));

        return $enabled === []
            ? $this->signal('guardrails', self::STATUS_PASS, 'Export governance guardrails disabled.')
            : $this->signal('guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    /**
     * @return array<int, string>
     */
    private function unregisteredGaps(): array
    {
        $gaps = [];
        foreach ($this->discovery->unregistered() as $route) {
            $gaps[] = $route['signature'].' (export-like route not registered)';
        }

        return $gaps;
    }

    /**
     * @return array<int, string>
     */
    private function meteredGuardGaps(): array
    {
        $gaps = [];
        $canonicalMeter = $this->registry->meterKey();
        $canonicalEvent = $this->registry->eventKey();

        foreach ($this->registry->metered() as $signature => $meta) {
            [$method, $uri] = array_pad(explode(' ', (string) $signature, 2), 2, '');
            $route = $this->findRoute($uri, $method);
            if ($route === null) {
                $gaps[] = "{$signature} (route missing)";
                continue;
            }

            $feature = (string) ($meta['entitlement'] ?? '');
            $limit = (string) ($meta['meter_key'] ?? $canonicalMeter);
            $middleware = array_values($route->gatherMiddleware());

            $lifecycleIdx = array_search('tenant.lifecycle', $middleware, true);
            $entitledIdx = array_search('tenant.entitled:'.$feature, $middleware, true);
            $usageIdx = array_search('tenant.usage.limit:'.$limit, $middleware, true);

            if ($lifecycleIdx === false) {
                $gaps[] = "{$uri} (no lifecycle guard)";
            }
            if ($entitledIdx === false) {
                $gaps[] = "{$uri} → {$feature} (no entitlement guard)";
            }
            if ($usageIdx === false) {
                $gaps[] = "{$uri} → {$limit} (no usage limit guard)";
            }

            // Ordering: lifecycle before entitlement before usage (EGC-R003/R004).
            if ($lifecycleIdx !== false && $entitledIdx !== false && (int) $lifecycleIdx > (int) $entitledIdx) {
                $gaps[] = "{$uri} (entitlement before lifecycle)";
            }
            if ($entitledIdx !== false && $usageIdx !== false && (int) $entitledIdx > (int) $usageIdx) {
                $gaps[] = "{$uri} (usage before entitlement)";
            }

            // Canonical taxonomy (EGC-R005/R006).
            if ($limit !== $canonicalMeter) {
                $gaps[] = "{$uri} (non-canonical meter key {$limit})";
            }
            if ((string) ($meta['event_key'] ?? $canonicalEvent) !== $canonicalEvent) {
                $gaps[] = "{$uri} (non-canonical event key)";
            }

            // Idempotency + sanitizer must be declared (EGC-R008/R009).
            if (trim((string) ($meta['idempotency_strategy'] ?? '')) === '') {
                $gaps[] = "{$uri} (missing idempotency strategy)";
            }
            if (($meta['metadata_sanitized'] ?? false) !== true) {
                $gaps[] = "{$uri} (metadata sanitizer not active)";
            }
        }

        return $gaps;
    }

    /**
     * @return array<int, string>
     */
    private function exemptionGaps(): array
    {
        $gaps = [];
        foreach ($this->registry->exempt() as $signature => $meta) {
            if (trim((string) ($meta['exempt_reason'] ?? '')) === '') {
                $gaps[] = "{$signature} (exemption without reason)";
            }
            if (($meta['metering_enabled'] ?? false) === true) {
                $gaps[] = "{$signature} (exempt route must not enable metering)";
            }
        }

        return $gaps;
    }

    private function findRoute(string $uri, ?string $method = null): ?Route
    {
        $needle = ltrim($uri, '/');

        foreach ($this->router->getRoutes() as $route) {
            if ($route->uri() !== $needle) {
                continue;
            }
            if ($method !== null && $method !== '' && ! in_array(strtoupper($method), $route->methods(), true)) {
                continue;
            }

            return $route;
        }

        return null;
    }

    /**
     * @param array<int, array{status:string}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
