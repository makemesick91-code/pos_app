<?php

namespace App\Services\TenantLifecycle;

use App\Http\Middleware\EnsureTenantLifecycleAllowed;
use App\Http\Middleware\EnsureTenantSubscriptionIsActive;
use Illuminate\Routing\Router;

/**
 * Sprint 25 — audits that the tenant lifecycle runtime enforcement is actually
 * wired (TLS-R003, TLS-R007). Detects a missing guard, an unregistered
 * middleware alias, an invalid config contract, or an enabled automation
 * guardrail — any of which is a FAIL.
 *
 * "Operational" tenant-scoped routes are identified as those guarded by
 * EnsureTenantSubscriptionIsActive (the Sprint 10 POS business surface). Every
 * such route MUST also carry EnsureTenantLifecycleAllowed, or a suspended tenant
 * could reach POS operations — a governance breach.
 */
class TenantLifecycleEnforcementAuditService
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
            $this->guardCoverageSignal(),
            $this->configContractSignal(),
            $this->guardrailSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'unguarded_operational_routes' => $this->unguardedOperationalRoutes(),
        ];
    }

    private function aliasSignal(): array
    {
        $aliases = $this->router->getMiddleware();

        return isset($aliases['tenant.lifecycle']) && $aliases['tenant.lifecycle'] === EnsureTenantLifecycleAllowed::class
            ? $this->signal('lifecycle_guard_alias', self::STATUS_PASS, 'tenant.lifecycle middleware alias is registered.')
            : $this->signal('lifecycle_guard_alias', self::STATUS_FAIL, 'tenant.lifecycle middleware alias is missing.');
    }

    private function guardCoverageSignal(): array
    {
        $missing = $this->unguardedOperationalRoutes();

        return $missing === []
            ? $this->signal('lifecycle_guard_coverage', self::STATUS_PASS, 'All operational tenant routes carry the lifecycle guard.')
            : $this->signal('lifecycle_guard_coverage', self::STATUS_FAIL, count($missing).' operational route(s) missing the lifecycle guard.');
    }

    private function configContractSignal(): array
    {
        $statuses = (array) config('tenant_lifecycle.statuses', []);
        $blocked = (array) config('tenant_lifecycle.blocked_statuses', []);
        $rules = (array) config('tenant_lifecycle.rules', []);

        $expectedRules = ['TLS-R001', 'TLS-R002', 'TLS-R003', 'TLS-R004', 'TLS-R005', 'TLS-R006', 'TLS-R007', 'TLS-R008', 'TLS-R009', 'TLS-R010'];
        $missingRules = array_values(array_diff($expectedRules, array_keys($rules)));

        if ($statuses === [] || $blocked === [] || $missingRules !== []) {
            return $this->signal('lifecycle_config_contract', self::STATUS_FAIL, 'Lifecycle config contract incomplete'.($missingRules === [] ? '.' : ' — missing rules: '.implode(', ', $missingRules)));
        }

        return $this->signal('lifecycle_config_contract', self::STATUS_PASS, count($statuses).' statuses, '.count($blocked).' blocked, '.count($rules).' rules locked.');
    }

    private function guardrailSignal(): array
    {
        $flags = [
            'auto_tenant_suspension_allowed',
            'auto_tenant_reactivation_allowed',
            'dunning_can_override_manual_suspension_allowed',
            'renewal_can_override_manual_suspension_allowed',
            'client_side_enforcement_authoritative',
            'public_tenant_suspension_api_allowed',
            'tenant_status_computed_in_controller_allowed',
            'real_notification_sending_allowed',
            'real_tenant_hard_delete_allowed',
        ];

        $enabled = [];
        foreach ($flags as $flag) {
            if (config('tenant_lifecycle.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return $enabled === []
            ? $this->signal('lifecycle_guardrails', self::STATUS_PASS, count($flags).' automation guardrails disabled.')
            : $this->signal('lifecycle_guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    /**
     * @return array<int, string>
     */
    public function unguardedOperationalRoutes(): array
    {
        $missing = [];

        foreach ($this->router->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $isOperational = in_array(EnsureTenantSubscriptionIsActive::class, $middleware, true);
            if (! $isOperational) {
                continue;
            }

            if (! in_array(EnsureTenantLifecycleAllowed::class, $middleware, true)) {
                $missing[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        return $missing;
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
