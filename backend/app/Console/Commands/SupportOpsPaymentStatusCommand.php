<?php

namespace App\Console\Commands;

use App\Services\SupportOperations\SupportPaymentViewerService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:payment-status. Read-only payment intent / gateway
 * event diagnostics (SUP-R009). No mutation, no settlement replay. No PII/secrets.
 */
class SupportOpsPaymentStatusCommand extends Command
{
    protected $signature = 'support-ops:payment-status {--tenant= : Tenant id or code} {--json}';

    protected $description = 'Show read-only payment intent/gateway event diagnostics for a tenant.';

    public function handle(SupportPaymentViewerService $payment): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));
        if ($tenant === null) {
            $this->line('No tenant specified (or not found); pass --tenant=<id|code>.');

            return self::SUCCESS;
        }

        $data = $payment->summary($tenant->id);
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Payment status for tenant #'.$tenant->id);
        $this->line('  intents: '.$data['intent_count'].' by_status: '.json_encode($data['intents_by_status']));
        $this->line('  gateway_events: '.$data['gateway_event_count'].' by_status: '.json_encode($data['gateway_events_by_status']));

        return self::SUCCESS;
    }
}
