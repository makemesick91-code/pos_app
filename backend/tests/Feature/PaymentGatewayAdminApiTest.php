<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use App\Services\PaymentGateway\Providers\MockQrisPaymentGatewayProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — admin gateway routes require platform.admin; the webhook route is
 * unauthenticated but signature-gated and is not a tenant mutation route
 * (PGW-R014/R015).
 */
class PaymentGatewayAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PGW-API']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
    }

    public function test_platform_admin_can_create_intent_via_api(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/gateway/invoices/{$this->invoice->id}/intents", [])
            ->assertStatus(201)
            ->assertJsonPath('data.amount', 99000)
            ->assertJsonPath('data.provider', 'mock');
    }

    public function test_non_admin_cannot_create_intent(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/gateway/invoices/{$this->invoice->id}/intents", [])
            ->assertForbidden();
    }

    public function test_admin_can_list_and_show_intents(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/tenant-billing/gateway/intents')
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/tenant-billing/gateway/intents/{$intent->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $intent->id);
    }

    public function test_admin_summaries_are_read_only_and_ok(): void
    {
        foreach (['provider-summary', 'settlement-summary', 'governance-summary'] as $path) {
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/v1/admin/tenant-billing/gateway/{$path}")
                ->assertOk();
        }
    }

    public function test_non_admin_cannot_read_gateway_governance(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/tenant-billing/gateway/governance-summary')
            ->assertForbidden();
    }

    public function test_webhook_route_accepts_valid_signed_paid_event(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);
        $mock = new MockQrisPaymentGatewayProvider;
        $payload = [
            'event_id' => 'evt_api_'.$intent->id,
            'event_type' => 'payment.settled',
            'reference' => $intent->provider_reference,
            'status' => 'settled',
            'amount' => $intent->amount,
            'currency' => $intent->currency,
        ];

        $this->withHeaders(['X-Signature' => $mock->signForTesting($payload)])
            ->postJson('/api/v1/payment-gateway/mock/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_webhook_route_rejects_invalid_signature(): void
    {
        $intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);
        $payload = [
            'event_id' => 'evt_bad_'.$intent->id,
            'reference' => $intent->provider_reference,
            'status' => 'settled',
            'amount' => $intent->amount,
        ];

        $this->withHeaders(['X-Signature' => 'invalid'])
            ->postJson('/api/v1/payment-gateway/mock/webhook', $payload)
            ->assertStatus(401);

        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_webhook_route_rejects_unknown_provider(): void
    {
        $this->postJson('/api/v1/payment-gateway/ghost/webhook', ['status' => 'settled'])
            ->assertStatus(404);
    }

    public function test_there_is_no_tenant_intent_creation_route(): void
    {
        // A tenant user hitting the admin route is forbidden; no non-admin route exists.
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $intent = TenantBillingPaymentIntent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/admin/tenant-billing/gateway/intents/{$intent->id}")
            ->assertForbidden();
    }
}
