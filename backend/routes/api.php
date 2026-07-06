<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Sprint 0 only exposes an infrastructure health endpoint. Business POS
| features are introduced in later sprints per the foundation document:
| ../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'Aish POS Lite API',
        'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        'sprint' => 'Sprint 0',
    ]);
});
