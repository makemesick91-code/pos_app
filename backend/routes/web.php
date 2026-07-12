<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminTenantWebController;
use App\Http\Controllers\HealthCheckController;
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
    });
});
