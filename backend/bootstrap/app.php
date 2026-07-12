<?php

use App\Http\Middleware\EnsureDeviceIsRegistered;
use App\Http\Middleware\EnsureExportEntitled;
use App\Http\Middleware\EnsureFeatureEntitled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePlatformAdminWeb;
use App\Http\Middleware\EnsureReportEntitled;
use App\Http\Middleware\EnsureTenantCanWrite;
use App\Http\Middleware\EnsureTenantEntitled;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantLifecycleAllowed;
use App\Http\Middleware\EnsureTenantSubscriptionIsActive;
use App\Http\Middleware\EnsureTenantUsageLimitAvailable;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.context' => SetTenantContext::class,
            'subscription.active' => EnsureTenantSubscriptionIsActive::class,
            'tenant.lifecycle' => EnsureTenantLifecycleAllowed::class,
            'tenant.entitled' => EnsureTenantEntitled::class,
            'tenant.usage.limit' => EnsureTenantUsageLimitAvailable::class,
            'device.registered' => EnsureDeviceIsRegistered::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            // UIX-3 — session/web variant of the platform-admin gate for the
            // browser SaaS Control Center (/admin/*). Redirects instead of
            // returning JSON; same is_active + isPlatformAdmin predicate.
            'platform.admin.web' => EnsurePlatformAdminWeb::class,
            // Sprint 32 — plan entitlement runtime enforcement & subscription
            // access control. entitlement.write gates mutating operational
            // requests on the tenant billing/subscription/lifecycle state (reads
            // always pass); feature/export/report enforce plan entitlement and
            // audit denials. All run AFTER tenant.lifecycle so manual suspension
            // still wins (ENT-R013).
            'entitlement.write' => EnsureTenantCanWrite::class,
            'entitlement.feature' => EnsureFeatureEntitled::class,
            'entitlement.export' => EnsureExportEntitled::class,
            'entitlement.report' => EnsureReportEntitled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
