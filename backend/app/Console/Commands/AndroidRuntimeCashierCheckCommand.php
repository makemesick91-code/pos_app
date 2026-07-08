<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AndroidRuntime\AndroidRuntimeAccessService;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:cashier-check. Dry-run cashier runtime access check
 * (no mutation by default). With --tenant and --user it evaluates the real runtime
 * posture for an existing cashier without writing anything. Without ids it prints
 * the governance posture (operator roles + fail-closed behaviors).
 */
class AndroidRuntimeCashierCheckCommand extends Command
{
    protected $signature = 'android-runtime:cashier-check {--tenant= : Tenant id} {--user= : Cashier user id} {--json : Output JSON}';

    protected $description = 'Dry-run cashier runtime access check (no mutation).';

    public function handle(AndroidRuntimeAccessService $access): int
    {
        $tenantId = $this->option('tenant');
        $userId = $this->option('user');

        if ($tenantId === null || $userId === null) {
            $report = [
                'mode' => 'posture',
                'operator_roles' => config('android_runtime_governance.cashier.operator_roles'),
                'session_timeout_minutes' => config('android_runtime_governance.cashier.session_timeout_minutes'),
                'runtime_behavior' => config('android_runtime_governance.runtime_behavior'),
            ];
            $this->emit($report);

            return self::SUCCESS;
        }

        $tenant = Tenant::query()->find((int) $tenantId);
        $user = User::query()->find((int) $userId);

        if ($tenant === null || $user === null) {
            $this->error('Tenant or user not found.');

            return self::FAILURE;
        }

        $decision = $access->authorizeCashierSession($tenant, $user);
        $this->emit(['mode' => 'check'] + $decision->toArray());

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function emit(array $report): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($report as $k => $v) {
            $this->line($k.'='.(is_scalar($v) ? $v : json_encode($v)));
        }
    }
}
