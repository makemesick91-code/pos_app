<?php

namespace App\Services\UsageEventLedger;

use App\Http\Middleware\EnsureTenantUsageLimitAvailable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Sprint 27 — audits that report export metering enforcement is actually wired
 * (UEL-R009, UEL-R010, UEL-R011): every report export route must carry the
 * tenant lifecycle guard FIRST, then the report entitlement guard, then the usage
 * limit guard for the report export meter. The meter itself must be meterable and
 * the usage.limit alias must be registered. A missing guard, wrong ordering, a
 * non-meterable meter, or an enabled guardrail is a FAIL.
 */
class ReportExportMeteringEnforcementAuditService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->aliasSignal(),
            $this->routeCoverageSignal(),
            $this->meterableSignal(),
            $this->guardrailSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gaps' => $this->gaps(),
        ];
    }

    private function aliasSignal(): array
    {
        $aliases = $this->router->getMiddleware();

        return ($aliases['tenant.usage.limit'] ?? null) === EnsureTenantUsageLimitAvailable::class
            ? $this->signal('usage_guard_alias', self::STATUS_PASS, 'tenant.usage.limit alias registered.')
            : $this->signal('usage_guard_alias', self::STATUS_FAIL, 'tenant.usage.limit middleware alias missing.');
    }

    private function routeCoverageSignal(): array
    {
        $gaps = $this->gaps();

        return $gaps === []
            ? $this->signal('report_export_guard_coverage', self::STATUS_PASS, 'All report export routes carry lifecycle → entitlement → usage guards in order (UEL-R009/R010).')
            : $this->signal('report_export_guard_coverage', self::STATUS_FAIL, count($gaps).' report export route gap(s): '.implode('; ', $gaps));
    }

    private function meterableSignal(): array
    {
        $meterKey = (string) config('usage_event_ledger.report_export_meter_key', 'reports.exports.monthly');
        // Literal (dotted) array key — read the array, not a config dot-path.
        $limits = (array) config('tenant_plan.usage_limits', []);

        return (bool) ($limits[$meterKey]['meterable'] ?? false)
            ? $this->signal('meter_live', self::STATUS_PASS, $meterKey.' is meterable and metered from the ledger (UEL-R006).')
            : $this->signal('meter_live', self::STATUS_FAIL, $meterKey.' is not meterable — report export metering is not live.');
    }

    private function guardrailSignal(): array
    {
        $flags = ['failed_export_counts_usage_allowed', 'client_side_report_export_authoritative', 'usage_ledger_mutable_in_runtime_allowed'];
        $enabled = array_values(array_filter($flags, fn ($f) => config('usage_event_ledger.'.$f) === true));

        return $enabled === []
            ? $this->signal('guardrails', self::STATUS_PASS, 'Report export metering guardrails disabled.')
            : $this->signal('guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    /**
     * @return array<int, string>
     */
    public function gaps(): array
    {
        $required = (array) config('usage_event_ledger.report_export_guarded_routes', []);
        $gaps = [];

        foreach ($required as $signature => $meta) {
            [$method, $uri] = array_pad(explode(' ', (string) $signature, 2), 2, '');
            $route = $this->findRoute($uri, $method);
            if ($route === null) {
                $gaps[] = "{$signature} (route missing)";
                continue;
            }

            $feature = (string) ($meta['feature'] ?? '');
            $limit = (string) ($meta['limit'] ?? '');
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

            // Ordering: lifecycle before entitlement before usage (UEL-R009/R010).
            if ($lifecycleIdx !== false && $entitledIdx !== false && (int) $lifecycleIdx > (int) $entitledIdx) {
                $gaps[] = "{$uri} (entitlement before lifecycle)";
            }
            if ($entitledIdx !== false && $usageIdx !== false && (int) $entitledIdx > (int) $usageIdx) {
                $gaps[] = "{$uri} (usage before entitlement)";
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
