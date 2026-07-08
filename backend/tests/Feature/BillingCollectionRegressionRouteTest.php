<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BillingCollectionRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    private function routeUris(): array
    {
        return collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();
    }

    public function test_core_business_and_admin_routes_still_registered(): void
    {
        $uris = $this->routeUris();

        foreach ([
            'api/v1/auth/login',
            'api/v1/products',
            'api/v1/sales',
            'api/v1/admin/tenants',
            'api/v1/admin/tenant-onboarding',
            'api/v1/admin/saas-packages',
            'api/v1/admin/public-website-pages',
            'api/v1/admin/sales-leads',
        ] as $uri) {
            $this->assertContains($uri, $uris, "Missing route: {$uri}");
        }
    }

    public function test_public_website_routes_still_work(): void
    {
        $this->getJson('/api/health')->assertOk();
        $uris = $this->routeUris();
        $this->assertContains('packages', $uris);
    }

    public function test_billing_collection_routes_registered_and_protected(): void
    {
        $uris = $this->routeUris();
        foreach ([
            'api/v1/admin/billing/accounts',
            'api/v1/admin/billing/cycles',
            'api/v1/admin/billing/invoices',
            'api/v1/admin/billing/readiness',
            'api/v1/admin/billing/go-no-go',
        ] as $uri) {
            $this->assertContains($uri, $uris, "Missing billing route: {$uri}");
        }

        // Protected: unauthenticated is blocked.
        $this->getJson('/api/v1/admin/billing/accounts')->assertStatus(401);
    }

    public function test_prior_and_sprint_23_commands_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ([
            'production:readiness-check', 'release:go-no-go',
            'commercial:launch-go-no-go', 'public-website:go-no-go',
            'sales-pipeline:go-no-go',
            'billing-collection:readiness', 'billing-collection:invoice-summary',
            'billing-collection:collection-summary', 'billing-collection:go-no-go',
        ] as $command) {
            $this->assertContains($command, $registered, "Missing command: {$command}");
        }
    }
}
