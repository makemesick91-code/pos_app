<?php

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request, hydrated by SetTenantContext middleware.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sprint 21 — rate limit the public interest-only lead endpoint so the
        // unauthenticated form cannot be abused. Interest-only; never provisions.
        RateLimiter::for('public-interest', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
