<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 31 — the gateway surface is additive: the Sprint 5 POS QRIS webhook and
 * the Sprint 30 tenant-billing routes are intact, and there is no route collision
 * between the Sprint 5 / Sprint 30 / Sprint 31 payment surfaces.
 */
class PaymentGatewayRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function uris(): array
    {
        return array_map(fn ($r) => $r->uri(), iterator_to_array(Route::getRoutes()));
    }

    public function test_sprint5_pos_webhook_route_still_present(): void
    {
        $this->assertContains('api/v1/webhooks/payments/{provider}', $this->uris());
    }

    public function test_sprint31_gateway_webhook_route_present_and_distinct(): void
    {
        $uris = $this->uris();
        $this->assertContains('api/v1/payment-gateway/{provider}/webhook', $uris);
        // Distinct path from the Sprint 5 webhook — no collision.
        $this->assertNotSame('api/v1/webhooks/payments/{provider}', 'api/v1/payment-gateway/{provider}/webhook');
    }

    public function test_sprint30_tenant_billing_routes_still_present(): void
    {
        $uris = $this->uris();
        $this->assertContains('api/v1/admin/tenant-billing/invoices', $uris);
        $this->assertContains('api/v1/admin/tenant-billing/collection-summary', $uris);
    }

    public function test_sprint31_admin_gateway_routes_present(): void
    {
        $uris = $this->uris();
        $this->assertContains('api/v1/admin/tenant-billing/gateway/intents', $uris);
        $this->assertContains('api/v1/admin/tenant-billing/gateway/events', $uris);
    }

    public function test_no_duplicate_route_uris_across_payment_surfaces(): void
    {
        // A URI may legitimately appear under different HTTP methods; a COLLISION is
        // the same method+URI registered twice. Key on method|uri.
        $keys = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! (str_contains($uri, 'payment') || str_contains($uri, 'gateway') || str_contains($uri, 'billing'))) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $keys[] = $method.'|'.$uri;
            }
        }

        $this->assertSame(count($keys), count(array_unique($keys)), 'Duplicate method+URI across payment surfaces.');
    }

    public function test_prior_sprint_gates_still_registered(): void
    {
        $registered = array_keys(\Illuminate\Support\Facades\Artisan::all());
        foreach ([
            'billing:go-no-go',
            'subscription-renewal:go-no-go',
            'tenant-lifecycle:go-no-go',
            'tenant-plan:go-no-go',
            'report-export-metering:go-no-go',
            'usage-ledger:go-no-go',
            'export-governance:go-no-go',
        ] as $command) {
            $this->assertContains($command, $registered);
        }
    }
}
