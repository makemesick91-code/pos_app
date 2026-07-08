<?php

namespace Tests\Feature;

use App\Models\SaasBillingAccount;
use App\Models\Tenant;
use App\Services\BillingCollection\BillingAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingAccountService
    {
        return app(BillingAccountService::class);
    }

    public function test_can_create_account_linked_to_existing_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $account = $this->service()->create([
            'tenant_id' => $tenant->id,
            'billing_name' => 'Toko Maju',
            'billing_email' => 'billing@example.com',
        ]);

        $this->assertSame($tenant->id, $account->tenant_id);
        $this->assertSame(SaasBillingAccount::STATUS_ACTIVE, $account->status);
        $this->assertNotEmpty($account->account_reference);
    }

    public function test_creating_account_does_not_create_tenant(): void
    {
        $before = Tenant::query()->count();

        $this->service()->create(['billing_name' => 'No Tenant Account']);

        $this->assertSame($before, Tenant::query()->count());
    }

    public function test_status_change_does_not_suspend_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $account = $this->service()->create([
            'tenant_id' => $tenant->id,
            'billing_name' => 'Linked',
        ]);

        $this->service()->update($account, ['status' => SaasBillingAccount::STATUS_SUSPENDED_MANUAL_REVIEW]);

        $this->assertSame(SaasBillingAccount::STATUS_SUSPENDED_MANUAL_REVIEW, $account->fresh()->status);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
    }

    public function test_secret_like_values_are_redacted(): void
    {
        $account = $this->service()->create([
            'billing_name' => 'note api_key: sk_live_leak',
            'billing_address' => 'password: hunter2',
            'metadata' => ['token' => 'ghp_secret', 'ok' => 'value'],
        ]);

        $this->assertStringNotContainsString('sk_live_leak', (string) $account->billing_name);
        $this->assertStringNotContainsString('hunter2', (string) $account->billing_address);
        $this->assertSame('[REDACTED]', $account->metadata['token']);
        $this->assertSame('value', $account->metadata['ok']);
    }

    public function test_summary_reports_by_status(): void
    {
        $this->service()->create(['billing_name' => 'A']);
        $this->service()->create(['billing_name' => 'B', 'status' => SaasBillingAccount::STATUS_ON_HOLD]);

        $summary = $this->service()->summary();

        $this->assertSame('GO', $summary['decision']);
        $this->assertSame(2, $summary['total_accounts']);
        $this->assertFalse($summary['auto_tenant_suspension']);
    }
}
