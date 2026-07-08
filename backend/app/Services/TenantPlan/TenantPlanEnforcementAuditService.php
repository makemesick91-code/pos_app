<?php

namespace App\Services\TenantPlan;

use App\Http\Middleware\EnsureTenantEntitled;
use App\Http\Middleware\EnsureTenantUsageLimitAvailable;
use Illuminate\Routing\Router;

/**
 * Sprint 26 — audits that the tenant plan entitlement/usage runtime enforcement
 * is actually wired (TPE-R002, TPE-R003) and that tenant lifecycle enforcement
 * runs first (TPE-R004).
 *
 * For every entitlement-guarded route (config tenant_plan.entitlement_guarded_
 * routes) the route MUST carry EnsureTenantEntitled with the required feature AND
 * EnsureTenantLifecycleAllowed. For every usage-guarded mutation the route MUST
 * carry EnsureTenantUsageLimitAvailable with the required limit AND the lifecycle
 * guard. A missing guard, an unregistered alias, an incomplete config contract,
 * or an enabled automation guardrail is a FAIL.
 */
class TenantPlanEnforcementAuditService
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
            $this->entitlementCoverageSignal(),
            $this->usageCoverageSignal(),
            $this->lifecyclePrecedenceSignal(),
            $this->configContractSignal(),
            $this->guardrailSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'entitlement_gaps' => $this->entitlementGaps(),
            'usage_gaps' => $this->usageGaps(),
        ];
    }

    private function aliasSignal(): array
    {
        $aliases = $this->router->getMiddleware();

        $ok = ($aliases['tenant.entitled'] ?? null) === EnsureTenantEntitled::class
            && ($aliases['tenant.usage.limit'] ?? null) === EnsureTenantUsageLimitAvailable::class;

        return $ok
            ? $this->signal('guard_aliases', self::STATUS_PASS, 'tenant.entitled and tenant.usage.limit aliases are registered.')
            : $this->signal('guard_aliases', self::STATUS_FAIL, 'Entitlement/usage middleware aliases are missing.');
    }

    private function entitlementCoverageSignal(): array
    {
        $gaps = $this->entitlementGaps();

        return $gaps === []
            ? $this->signal('entitlement_guard_coverage', self::STATUS_PASS, 'All entitlement-guarded routes carry EnsureTenantEntitled with the required feature.')
            : $this->signal('entitlement_guard_coverage', self::STATUS_FAIL, count($gaps).' entitlement route gap(s): '.implode('; ', $gaps));
    }

    private function usageCoverageSignal(): array
    {
        $gaps = $this->usageGaps();

        return $gaps === []
            ? $this->signal('usage_guard_coverage', self::STATUS_PASS, 'All usage-guarded mutations carry EnsureTenantUsageLimitAvailable with the required limit.')
            : $this->signal('usage_guard_coverage', self::STATUS_FAIL, count($gaps).' usage route gap(s): '.implode('; ', $gaps));
    }

    private function lifecyclePrecedenceSignal(): array
    {
        $missing = [];

        foreach ($this->guardedRoutes() as $entry) {
            $route = $entry['route'];
            $middleware = array_values($route->gatherMiddleware());

            $lifecycleIndex = array_search('tenant.lifecycle', $middleware, true);
            if ($lifecycleIndex === false) {
                $missing[] = $route->uri().' (no lifecycle guard)';
                continue;
            }

            // The entitlement/usage guard must run AFTER the lifecycle guard so a
            // suspended tenant is blocked with TENANT_SUSPENDED first (TPE-R004).
            foreach ($middleware as $index => $entryName) {
                $isPlanGuard = str_starts_with((string) $entryName, 'tenant.entitled:')
                    || str_starts_with((string) $entryName, 'tenant.usage.limit:');
                if ($isPlanGuard && $index < (int) $lifecycleIndex) {
                    $missing[] = $route->uri().' (plan guard before lifecycle)';
                    break;
                }
            }
        }

        return $missing === []
            ? $this->signal('lifecycle_precedence', self::STATUS_PASS, 'Every entitlement/usage-guarded route carries the tenant lifecycle guard first (lifecycle precedence, TPE-R004).')
            : $this->signal('lifecycle_precedence', self::STATUS_FAIL, count($missing).' guarded route(s) violate lifecycle precedence: '.implode(', ', array_unique($missing)));
    }

    private function configContractSignal(): array
    {
        $rules = (array) config('tenant_plan.rules', []);
        $expected = ['TPE-R001', 'TPE-R002', 'TPE-R003', 'TPE-R004', 'TPE-R005', 'TPE-R006', 'TPE-R007', 'TPE-R008', 'TPE-R009', 'TPE-R010', 'TPE-R011', 'TPE-R012'];
        $missing = array_values(array_diff($expected, array_keys($rules)));

        $entitlements = (array) config('tenant_plan.entitlements', []);
        $limits = (array) config('tenant_plan.usage_limits', []);
        $plans = (array) config('tenant_plan.plans', []);

        if ($missing !== [] || $entitlements === [] || $limits === [] || $plans === []) {
            return $this->signal('plan_config_contract', self::STATUS_FAIL, 'Plan config contract incomplete'.($missing === [] ? '.' : ' — missing rules: '.implode(', ', $missing)));
        }

        return $this->signal('plan_config_contract', self::STATUS_PASS, count($plans).' plans, '.count($entitlements).' entitlements, '.count($limits).' limits, '.count($rules).' rules locked.');
    }

    private function guardrailSignal(): array
    {
        $flags = [
            'client_side_entitlement_authoritative',
            'suspended_tenant_can_be_overridden_allowed',
            'entitlement_computed_in_controller_allowed',
            'plan_assignment_without_platform_admin_allowed',
            'override_without_reason_allowed',
            'real_billing_charge_on_plan_change_allowed',
        ];

        $enabled = [];
        foreach ($flags as $flag) {
            if (config('tenant_plan.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return $enabled === []
            ? $this->signal('plan_guardrails', self::STATUS_PASS, count($flags).' automation guardrails disabled.')
            : $this->signal('plan_guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    /**
     * @return array<int, string>
     */
    public function entitlementGaps(): array
    {
        $required = (array) config('tenant_plan.entitlement_guarded_routes', []);
        $gaps = [];

        foreach ($required as $feature => $uris) {
            foreach ((array) $uris as $uri) {
                $route = $this->findRoute($uri);
                if ($route === null) {
                    $gaps[] = "{$uri} (route missing)";
                    continue;
                }

                $middleware = $route->gatherMiddleware();
                if (! in_array('tenant.entitled:'.$feature, $middleware, true)) {
                    $gaps[] = "{$uri} → {$feature}";
                }
            }
        }

        return $gaps;
    }

    /**
     * @return array<int, string>
     */
    public function usageGaps(): array
    {
        $required = (array) config('tenant_plan.usage_guarded_routes', []);
        $gaps = [];

        foreach ($required as $limitKey => $signature) {
            [$method, $uri] = array_pad(explode(' ', (string) $signature, 2), 2, '');
            $route = $this->findRoute($uri, $method);
            if ($route === null) {
                $gaps[] = "{$signature} (route missing)";
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (! in_array('tenant.usage.limit:'.$limitKey, $middleware, true)) {
                $gaps[] = "{$signature} → {$limitKey}";
            }
        }

        return $gaps;
    }

    /**
     * @return array<int, array{route: \Illuminate\Routing\Route}>
     */
    private function guardedRoutes(): array
    {
        $out = [];
        $seen = [];

        $entitlementUris = [];
        foreach ((array) config('tenant_plan.entitlement_guarded_routes', []) as $uris) {
            foreach ((array) $uris as $uri) {
                $entitlementUris[] = ['uri' => $uri, 'method' => null];
            }
        }
        foreach ((array) config('tenant_plan.usage_guarded_routes', []) as $signature) {
            [$method, $uri] = array_pad(explode(' ', (string) $signature, 2), 2, '');
            $entitlementUris[] = ['uri' => $uri, 'method' => $method];
        }

        foreach ($entitlementUris as $target) {
            $route = $this->findRoute($target['uri'], $target['method']);
            if ($route === null) {
                continue;
            }
            $key = implode('|', $route->methods()).' '.$route->uri();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['route' => $route];
        }

        return $out;
    }

    private function findRoute(string $uri, ?string $method = null): ?\Illuminate\Routing\Route
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
     * @param  array<int, array{status:string}>  $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_WARN) {
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
