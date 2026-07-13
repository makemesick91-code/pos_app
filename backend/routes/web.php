<?php

use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIncidentController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminObservabilityWebController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminTenantWebController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Owner\OwnerBillingController;
use App\Http\Controllers\Owner\OwnerDashboardController;
use App\Http\Controllers\Owner\OwnerDeviceController;
use App\Http\Controllers\Owner\OwnerLoginController;
use App\Http\Controllers\Owner\OwnerOperationsController;
use App\Http\Controllers\Owner\OwnerOutletController;
use App\Http\Controllers\Owner\OwnerSubscriptionController;
use App\Http\Controllers\Owner\OwnerSupportController;
use App\Http\Controllers\Owner\OwnerUsageController;
use App\Http\Controllers\PublicWebsite\LandingPageController;
use App\Http\Controllers\PublicWebsite\LeadInterestController;
use App\Http\Controllers\PublicWebsite\PackagePageController;
use App\Http\Controllers\PublicWebsite\PrivacyPageController;
use App\Http\Controllers\PublicWebsite\TermsPageController;
use Illuminate\Support\Facades\Route;

/*
 * Sprint 36 — public, minimal liveness/readiness endpoints (OBS-R001). These
 * return only { status, timestamp }: no tenant data, no environment secret, no DB
 * credential, no PII. Safe behind a public load balancer.
 */
Route::get('/health/live', [HealthCheckController::class, 'live']);
Route::get('/health/ready', [HealthCheckController::class, 'ready']);

/*
 * Sprint 21 — public website / landing page. Unauthenticated by design, read-only,
 * fast, and secret-free. These pages NEVER create a tenant/user/subscription/
 * device, NEVER activate billing, and NEVER open self-service signup. The lead
 * interest endpoint is interest-only, requires consent, and is rate-limited.
 */
Route::get('/', [LandingPageController::class, 'index']);
Route::get('/packages', [PackagePageController::class, 'index']);
Route::get('/privacy', [PrivacyPageController::class, 'index']);
Route::get('/terms', [TermsPageController::class, 'index']);
Route::get('/thank-you', [LandingPageController::class, 'thankYou']);

Route::post('/interest', [LeadInterestController::class, 'store'])
    ->middleware('throttle:'.config('public_website.lead_rate_limit', 'public-interest'));

/*
 * UIX-3 — Platform Admin browser login + SaaS Control Center (session/cookie).
 *
 * Distinct surface from the API (Sanctum) and from the public website. Guarded
 * by platform.admin.web (is_active + isPlatformAdmin, deny-by-default). Read-only
 * control center: NO tenant mutation routes exist in this foundation sprint.
 * NOTE: while HTTPS/domain is unavailable, this surface must be reached only via
 * an encrypted operator channel (SSH tunnel / VPN / private network); public
 * plaintext HTTP admin access remains NO-GO (see docs/security/uix-3-*).
 */
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminLoginController::class, 'logout'])
        ->middleware('platform.admin.web')
        ->name('logout');

    Route::middleware('platform.admin.web')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/tenants', [AdminTenantWebController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}', [AdminTenantWebController::class, 'show'])->name('tenants.show');

        // UIX-5 — Platform Admin Billing Operations. Read-only, platform-scoped;
        // NO mutation routes (UIX5-R015/R016).
        Route::get('/tenants/{tenant}/billing', [AdminBillingController::class, 'tenantBilling'])
            ->name('tenants.billing');
        Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing');
        Route::get('/billing/invoices', [AdminBillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('/billing/invoices/{invoice}', [AdminBillingController::class, 'showInvoice'])
            ->whereNumber('invoice')->name('billing.invoices.show');
        Route::get('/billing/invoices/{invoice}/download', [AdminBillingController::class, 'downloadInvoice'])
            ->whereNumber('invoice')->name('billing.invoices.download');

        // UIX-6 — Platform Admin Support, Observability & Incident Console.
        // Read-only, platform-scoped; NO mutation routes (UIX6-R015/R016). Reuses
        // the Sprint 35/36 canonical services; observability presents truthful
        // freshness (UIX6-R011), incidents are read verbatim (UIX6-R014).
        Route::get('/support', [AdminSupportController::class, 'index'])->name('support');
        Route::get('/support/tenants', [AdminSupportController::class, 'tenants'])->name('support.tenants');
        Route::get('/support/tenants/{tenant}', [AdminSupportController::class, 'tenantDetail'])
            ->name('support.tenants.show');
        Route::get('/observability', [AdminObservabilityWebController::class, 'index'])->name('observability');
        Route::get('/incidents', [AdminIncidentController::class, 'index'])->name('incidents.index');
        Route::get('/incidents/{incident}', [AdminIncidentController::class, 'show'])
            ->whereNumber('incident')->name('incidents.show');
    });
});

/*
 * UIX-4 — Tenant Owner browser login + Owner Web Console (session/cookie).
 *
 * A distinct application surface from the public website, the Platform Admin
 * Console (/admin/*), and the Android/API (Sanctum) surface. Runs on its own
 * `owner` session guard, guarded by tenant.owner.web (is_active + tenant_owner
 * role + a resolvable tenant, deny-by-default). The tenant is always derived
 * server-side from the authenticated owner's own record — never from a route
 * parameter, query string, header, or cookie (UIX4-R004/R005). Read-only first:
 * no tenant business mutation routes exist in this foundation sprint
 * (UIX4-R011). Outlet/device ids are resolved only within the owner's tenant.
 * NOTE: while HTTPS/domain is unavailable, this surface must be reached only via
 * an encrypted operator/user channel; public plaintext HTTP access with real
 * tenant data remains NO-GO (UIX4-R019, docs/security/uix-4-*).
 */
Route::prefix('owner')->name('owner.')->group(function (): void {
    Route::get('/login', [OwnerLoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [OwnerLoginController::class, 'login'])->name('login.store');
    Route::post('/logout', [OwnerLoginController::class, 'logout'])
        ->middleware('tenant.owner.web')
        ->name('logout');

    Route::middleware('tenant.owner.web')->group(function (): void {
        Route::get('/', [OwnerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/outlets', [OwnerOutletController::class, 'index'])->name('outlets.index');
        Route::get('/outlets/{outlet}', [OwnerOutletController::class, 'show'])
            ->whereNumber('outlet')->name('outlets.show');
        Route::get('/devices', [OwnerDeviceController::class, 'index'])->name('devices.index');
        Route::get('/devices/{device}', [OwnerDeviceController::class, 'show'])
            ->whereNumber('device')->name('devices.show');
        Route::get('/subscription', [OwnerSubscriptionController::class, 'index'])->name('subscription');
        Route::get('/usage', [OwnerUsageController::class, 'index'])->name('usage');
        Route::get('/operations', [OwnerOperationsController::class, 'index'])->name('operations');

        // UIX-5 — Tenant Owner Billing Center. Read-only, tenant-scoped to the
        // owner's own tenant only. Invoice ids are resolved server-side within
        // the tenant; a foreign/unknown id is 404 (UIX5-R003/R006). No public
        // invoice URL exists — the download route is authenticated (UIX5-R007).
        Route::get('/billing', [OwnerBillingController::class, 'index'])->name('billing');
        Route::get('/billing/invoices', [OwnerBillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('/billing/invoices/{invoice}', [OwnerBillingController::class, 'showInvoice'])
            ->whereNumber('invoice')->name('billing.invoices.show');
        Route::get('/billing/invoices/{invoice}/download', [OwnerBillingController::class, 'downloadInvoice'])
            ->whereNumber('invoice')->name('billing.invoices.download');

        // UIX-6 — Tenant Owner Support / Operational view. Read-only, STRICTLY
        // tenant-scoped to the owner's own tenant (server-resolved). Incident ids
        // are resolved only within the tenant; a foreign/unknown id is 404
        // (UIX6-R004/R008). The owner never sees platform-global observability or
        // another tenant's data (UIX6-R005/R010).
        Route::get('/support', [OwnerSupportController::class, 'index'])->name('support');
        Route::get('/support/incidents/{incident}', [OwnerSupportController::class, 'showIncident'])
            ->whereNumber('incident')->name('support.incidents.show');
    });
});
