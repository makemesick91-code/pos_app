<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SupportOperations\SupportTenantHealthService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:tenant-health. Shows a tenant health summary computed
 * through SupportTenantHealthService (SUP-R002). No PII/secrets. With --tenant
 * (id or code) shows the full overview; otherwise lists brief health for tenants.
 */
class SupportOpsTenantHealthCommand extends Command
{
    protected $signature = 'support-ops:tenant-health {--tenant= : Tenant id or code} {--limit=25} {--json}';

    protected $description = 'Show tenant health overview (billing/payment/entitlement/onboarding/device/sync).';

    public function handle(SupportTenantHealthService $health): int
    {
        $tenantOpt = $this->option('tenant');

        if ($tenantOpt !== null && $tenantOpt !== '') {
            $tenant = $this->resolve($tenantOpt);
            if ($tenant === null) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }
            $data = $health->overview($tenant);
            $this->emit($data);

            return self::SUCCESS;
        }

        $limit = max(1, min((int) $this->option('limit'), 100));
        $rows = Tenant::query()->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Tenant $t) => $health->briefStatus($t))->all();
        $this->emit(['count' => count($rows), 'tenants' => $rows]);

        return self::SUCCESS;
    }

    private function resolve(string $value): ?Tenant
    {
        return is_numeric($value)
            ? Tenant::query()->find((int) $value)
            : Tenant::query()->where('code', $value)->first();
    }

    private function emit(array $data): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }
        if (isset($data['tenants'])) {
            $this->line('Tenant health ('.$data['count'].')');
            foreach ($data['tenants'] as $t) {
                $this->line(sprintf('  #%d %s — %s [%s]', $t['tenant_id'], $t['tenant_code'], $t['health_status'], implode(',', $t['reason_codes'])));
            }

            return;
        }
        $this->line(sprintf('Tenant #%d %s — health: %s', $data['tenant_id'], $data['tenant_code'], $data['health_status']));
        $this->line('  reasons: '.implode(', ', $data['reason_codes']));
    }
}
