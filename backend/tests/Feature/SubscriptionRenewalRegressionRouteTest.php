<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 24 — regression guard. Prior-sprint gate commands and key routes must
 * remain registered; new renewal routes must exist behind platform.admin.
 */
class SubscriptionRenewalRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_prior_sprint_gate_commands_remain_registered(): void
    {
        $registered = array_keys(Artisan::all());
        foreach ((array) config('subscription_renewal.required_commands') as $command) {
            $this->assertContains($command, $registered, "Missing prior-sprint command: {$command}");
        }
    }

    public function test_new_renewal_commands_registered(): void
    {
        $registered = array_keys(Artisan::all());
        foreach ([
            'subscription-renewal:readiness',
            'subscription-renewal:candidate-summary',
            'subscription-renewal:dunning-summary',
            'subscription-renewal:go-no-go',
        ] as $command) {
            $this->assertContains($command, $registered);
        }
    }

    public function test_renewal_routes_registered_behind_platform_admin(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($u) => str_contains((string) $u, 'subscription-renewal'));

        $this->assertNotEmpty($uris);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_contains($route->uri(), 'subscription-renewal')) {
                $this->assertContains('platform.admin', $route->gatherMiddleware(), $route->uri().' must be platform.admin');
            }
        }
    }

    public function test_billing_collection_gate_still_registered(): void
    {
        $registered = array_keys(Artisan::all());
        $this->assertContains('billing-collection:go-no-go', $registered);
        $this->assertContains('sales-pipeline:go-no-go', $registered);
    }
}
