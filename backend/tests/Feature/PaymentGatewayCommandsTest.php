<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — the gateway commands are safe: dry-run by default, deterministic,
 * and never leak secrets (PGW-R016/R018).
 */
class PaymentGatewayCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PGW-CMD']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
    }

    public function test_provider_summary_runs(): void
    {
        $this->artisan('payment-gateway:provider-summary')->assertExitCode(0);
    }

    public function test_intent_create_is_dry_run_by_default(): void
    {
        $this->artisan('payment-gateway:intent-create', ['--invoice' => $this->invoice->id])
            ->assertExitCode(0);

        $this->assertSame(0, TenantBillingPaymentIntent::query()->count());
    }

    public function test_intent_create_execute_persists(): void
    {
        $this->artisan('payment-gateway:intent-create', ['--invoice' => $this->invoice->id, '--execute' => true])
            ->assertExitCode(0);

        $this->assertSame(1, TenantBillingPaymentIntent::query()->count());
    }

    public function test_webhook_simulate_dry_run_does_not_create_event(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);

        $this->artisan('payment-gateway:webhook-simulate', ['--intent' => $intent->id, '--status' => 'paid'])
            ->assertExitCode(0);

        $this->assertSame(0, \App\Models\TenantBillingGatewayEvent::query()->count());
    }

    public function test_webhook_simulate_execute_paid_settles(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);

        $this->artisan('payment-gateway:webhook-simulate', ['--intent' => $intent->id, '--status' => 'paid', '--execute' => true])
            ->assertExitCode(0);

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_webhook_simulate_invalid_signature_does_not_settle(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);

        $this->artisan('payment-gateway:webhook-simulate', ['--intent' => $intent->id, '--status' => 'invalid-signature', '--execute' => true])
            ->assertExitCode(0);

        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_event_and_settlement_summaries_run(): void
    {
        $this->artisan('payment-gateway:event-summary')->assertExitCode(0);
        $this->artisan('payment-gateway:settlement-summary')->assertExitCode(0);
    }

    public function test_go_no_go_returns_go(): void
    {
        $this->artisan('payment-gateway:go-no-go', ['--strict' => true])->assertExitCode(0);
    }

    public function test_provider_summary_json_has_no_secret(): void
    {
        $this->artisan('payment-gateway:provider-summary', ['--json' => true])->assertExitCode(0);
        // Also assert the config surface never emits a credential value.
        $json = (string) json_encode(app(\App\Services\PaymentGateway\PaymentGatewaySummaryService::class)->providerSummary());
        $this->assertStringNotContainsString('SERVER_KEY=', $json);
        $this->assertDoesNotMatchRegularExpression('/sk_live_|xnd_/', $json);
    }
}
