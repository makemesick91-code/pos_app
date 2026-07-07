<?php

use App\Http\Controllers\PublicWebsite\LandingPageController;
use App\Http\Controllers\PublicWebsite\LeadInterestController;
use App\Http\Controllers\PublicWebsite\PackagePageController;
use App\Http\Controllers\PublicWebsite\PrivacyPageController;
use App\Http\Controllers\PublicWebsite\TermsPageController;
use Illuminate\Support\Facades\Route;

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
