<?php

namespace App\Console\Commands;

use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\SupportOperations\SupportDeviceOperationsService;
use App\Services\SupportOperations\SupportException;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:device-action. Governed device revoke/reactivate from
 * the CLI (SUP-R012/R013). Dry-run by default; --execute performs the mutation and
 * requires --reason and --activation. Revoke calls the Sprint 34
 * DeviceRevocationService; reactivate is disabled and fails closed. Never prints a
 * raw token.
 */
class SupportOpsDeviceActionCommand extends Command
{
    protected $signature = 'support-ops:device-action
        {--activation= : Device activation id}
        {--action=revoke : revoke|reactivate}
        {--reason= : A governed reason code (required with --execute)}
        {--execute : Perform the mutation (otherwise dry-run)}
        {--json}';

    protected $description = 'Governed support device revoke/reactivate (dry-run by default).';

    public function handle(SupportDeviceOperationsService $devices): int
    {
        $action = (string) $this->option('action');
        if (! in_array($action, ['revoke', 'reactivate'], true)) {
            $this->error('Unknown action; use revoke or reactivate.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $activationId = $this->option('activation');
        $activation = $activationId !== null ? TenantDeviceActivation::query()->find((int) $activationId) : null;

        if (! $execute) {
            $this->emit([
                'mode' => 'dry-run',
                'action' => $action,
                'activation_id' => $activation?->id,
                'current_status' => $activation?->activation_status,
                'would_mutate' => false,
                'note' => $action === 'reactivate'
                    ? 'Reactivation is disabled by governance; re-activate via the standard device activation flow.'
                    : 'Pass --execute --reason=<code> to revoke.',
            ]);

            return self::SUCCESS;
        }

        if ($activation === null) {
            $this->error('--activation is required with --execute.');

            return self::FAILURE;
        }

        $reason = $this->option('reason');
        $actor = User::query()->where('is_platform_admin', true)->first();
        if ($actor === null) {
            $this->error('No platform-admin actor available to attribute the action.');

            return self::FAILURE;
        }

        try {
            if ($action === 'revoke') {
                $result = $devices->revoke($activation, $actor, $reason);
                $this->emit(['mode' => 'execute', 'action' => 'revoke', 'activation_id' => $result->id, 'status' => $result->activation_status, 'mutated' => true]);

                return self::SUCCESS;
            }

            $devices->reactivate($activation, $actor, $reason);
        } catch (SupportException $e) {
            $this->emit(['mode' => 'execute', 'action' => $action, 'error' => $e->errorCode, 'message' => $e->getMessage(), 'mutated' => false]);

            return $action === 'reactivate' ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function emit(array $data): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }
        foreach ($data as $k => $v) {
            $this->line('  '.$k.': '.(is_scalar($v) || $v === null ? (string) $v : json_encode($v)));
        }
    }
}
