<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TenantContextController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Sprint 1 introduces the SaaS tenant foundation: Sanctum auth and a
| tenant-context diagnostic endpoint. Business POS features arrive in later
| sprints per the foundation document:
| ../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'Aish POS Lite API',
        'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        'sprint' => 'Sprint 1',
    ]);
});

Route::prefix('v1')->group(function () {
    // Public auth
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Authenticated (Sanctum) endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Tenant-aware endpoints: user + tenant/store must be active, and the
        // tenant context is resolved (and any X-Store-ID validated) here.
        Route::middleware(['tenant.active', 'tenant.context'])->group(function () {
            Route::get('/tenant-context', [TenantContextController::class, 'show']);
        });
    });
});
