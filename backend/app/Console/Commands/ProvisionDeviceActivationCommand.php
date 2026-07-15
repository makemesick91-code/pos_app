<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Tenant;
use App\Services\AndroidRuntime\DeviceActivationService;
use Illuminate\Console\Command;

/**
 * UIX-8C-07 — securely issue a single-use, short-lived Android device-activation
 * code for a tenant (UIX8C-R217/R221). This wires the previously-unused
 * `DeviceActivationService::prepare()` so a real operator can mint a genuine
 * activation code out-of-band, instead of relying on the dev-only auto-prepare
 * path (which self-asserts a device).
 *
 * Security (mirrors tenant:owner-provision):
 *  - The tenant is selected by a safe identifier (code or id) and must exist;
 *    the command never creates a tenant.
 *  - The raw activation code is printed EXACTLY ONCE to stdout for the operator
 *    to hand to the device out-of-band. Only its sha256 hash is persisted; the
 *    raw code is never logged, echoed to a log channel, or stored (ADR-R003,
 *    UIX8C-R219/R246).
 *  - The code is single-use: activate() consumes it by token hash and flips the
 *    activation to ACTIVATED, so a replay finds no pending token.
 *  - A TTL bounds the code's validity (default from
 *    android_runtime_governance.activation_token_ttl_minutes).
 *
 * Usage:
 *   php artisan device:provision-activation --tenant=ACME01 --store=3 --ttl=120
 */
class ProvisionDeviceActivationCommand extends Command
{
    protected $signature = 'device:provision-activation
        {--tenant= : Tenant code or id the device will be bound to}
        {--store= : Optional store/outlet id to bind the activation to}
        {--ttl= : Code validity in minutes (defaults to governance config)}
        {--json : Emit the safe activation record as JSON (never includes the raw code)}';

    protected $description = 'Issue a single-use, short-lived Android device-activation code for a tenant (no default credentials).';

    public function handle(DeviceActivationService $activation): int
    {
        $tenantRef = trim((string) $this->option('tenant'));
        if ($tenantRef === '') {
            $this->error('A --tenant (code or id) is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('code', $tenantRef)
            ->orWhere('id', ctype_digit($tenantRef) ? (int) $tenantRef : 0)
            ->first();

        if (! $tenant instanceof Tenant) {
            $this->error("Tenant [{$tenantRef}] not found.");

            return self::FAILURE;
        }

        $storeId = null;
        if ($this->option('store') !== null && $this->option('store') !== '') {
            $storeId = (int) $this->option('store');
            $store = Store::query()->where('tenant_id', $tenant->id)->find($storeId);
            if ($store === null) {
                $this->error("Store [{$storeId}] does not belong to tenant [{$tenant->id}].");

                return self::FAILURE;
            }
        }

        $ttl = $this->option('ttl') !== null && $this->option('ttl') !== ''
            ? max(1, (int) $this->option('ttl'))
            : null;

        $result = $activation->prepare(
            tenant: $tenant,
            storeId: $storeId,
            actor: null,
            ttlMinutes: $ttl,
        );

        /** @var \App\Models\TenantDeviceActivation $record */
        $record = $result['activation'];
        $rawCode = $result['token'];

        if ($this->option('json')) {
            // The safe record NEVER contains the raw code.
            $this->line((string) json_encode($record->toSafeArray(), JSON_PRETTY_PRINT));
        }

        $this->newLine();
        $this->info('Device activation code issued (single-use). Share it out-of-band with the device operator:');
        $this->newLine();
        $this->line('  Tenant       : '.$tenant->name.' (id '.$tenant->id.')');
        if ($storeId !== null) {
            $this->line('  Store/Outlet : id '.$storeId);
        }
        $this->line('  Expires at   : '.optional($record->expires_at)->toIso8601String());
        $this->newLine();
        $this->line('  ACTIVATION CODE: '.$rawCode);
        $this->newLine();
        $this->warn('This code is shown only once and is single-use. It is not stored or logged in plaintext.');

        return self::SUCCESS;
    }
}
