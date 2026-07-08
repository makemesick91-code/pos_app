<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\BillingPeriodService;
use App\Services\Billing\TenantInvoicePricingService;
use App\Services\Billing\TenantInvoiceService;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:invoice-generate. Default DRY-RUN; only `--apply` persists.
 *
 * Idempotent per tenant + period (BIL-R002): re-running never creates a duplicate
 * invoice. Amounts come from the plan pricing source of truth; a tenant with no
 * plan pricing is refused, never silently zeroed. `--apply` is audit-logged.
 * Output is redacted (counts + plan/amount only, no secrets).
 */
class BillingInvoiceGenerateCommand extends Command
{
    protected $signature = 'billing:invoice-generate
        {--tenant= : Tenant id (default: all tenants)}
        {--period= : Billing period key YYYY-MM (default: current period)}
        {--source=cli : Invoice source label}
        {--reason= : Reason recorded in the audit metadata}
        {--actor= : Actor label recorded in the audit metadata}
        {--apply : Persist invoices (otherwise dry-run)}
        {--json : Output JSON}';

    protected $description = 'Idempotently generate tenant invoices from active plan pricing (dry-run unless --apply).';

    public function handle(
        BillingPeriodService $periods,
        TenantInvoicePricingService $pricing,
        TenantInvoiceService $invoices,
    ): int {
        try {
            $period = $this->option('period')
                ? $periods->resolveForKey((string) $this->option('period'))
                : $periods->resolveForDate();
        } catch (BillingGovernanceException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $source = (string) $this->option('source') ?: 'cli';
        $actor = $this->option('actor') ? User::query()->where('is_platform_admin', true)->orderBy('id')->first() : null;

        $tenants = $this->option('tenant')
            ? Tenant::query()->whereKey((int) $this->option('tenant'))->get()
            : Tenant::query()->orderBy('id')->get();

        $results = [];
        foreach ($tenants as $tenant) {
            try {
                $price = $pricing->resolveForTenant($tenant);

                if ($apply) {
                    $invoice = $invoices->generate(
                        tenant: $tenant,
                        periodKey: $period->key,
                        source: $source,
                        actor: $actor,
                        metadata: ['reason' => $this->option('reason'), 'actor' => $this->option('actor')],
                    );
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'plan_key' => $invoice->plan_key,
                        'amount' => $invoice->total_amount,
                        'invoice_number' => $invoice->invoice_number,
                        'created' => $invoice->wasRecentlyCreated,
                        'status' => 'applied',
                    ];
                } else {
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'plan_key' => $price['plan_key'],
                        'amount' => $price['amount'],
                        'invoice_number' => '(dry-run)',
                        'created' => false,
                        'status' => 'dry-run',
                    ];
                }
            } catch (BillingGovernanceException $e) {
                $results[] = [
                    'tenant_id' => $tenant->id,
                    'plan_key' => null,
                    'amount' => null,
                    'invoice_number' => null,
                    'created' => false,
                    'status' => 'refused: '.$e->governanceCode,
                ];
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'period_key' => $period->key,
                'apply' => $apply,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line(($apply ? 'APPLY' : 'DRY-RUN').' — invoice generation for period '.$period->key);
        foreach ($results as $r) {
            $this->line("  tenant {$r['tenant_id']}: {$r['plan_key']} {$r['amount']} [{$r['status']}]");
        }

        return self::SUCCESS;
    }
}
