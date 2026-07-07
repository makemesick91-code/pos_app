<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DailyClosingController;
use App\Http\Controllers\Api\V1\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\InventoryCurrentStockController;
use App\Http\Controllers\Api\V1\InventoryMovementController;
use App\Http\Controllers\Api\V1\PaymentStatusController;
use App\Http\Controllers\Api\V1\Reports\DailySalesCsvExportController;
use App\Http\Controllers\Api\V1\Reports\DailySalesReportController;
use App\Http\Controllers\Api\V1\Reports\InventoryMovementSummaryController;
use App\Http\Controllers\Api\V1\Reports\PaymentSummaryReportController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductStorePriceController;
use App\Http\Controllers\Api\V1\ProductSyncController;
use App\Http\Controllers\Api\V1\QrisPaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\SaleCashPaymentController;
use App\Http\Controllers\Api\V1\SaleController;
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
        'sprint' => 'Sprint 5',
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

            // Sprint 2 — tenant-isolated product catalog.
            Route::apiResource('product-categories', ProductCategoryController::class);
            Route::apiResource('products', ProductController::class);
            Route::apiResource('product-store-prices', ProductStorePriceController::class);

            // Android incremental product/category sync.
            Route::get('/sync/products', [ProductSyncController::class, 'products']);
            Route::get('/sync/categories', [ProductSyncController::class, 'categories']);

            // Sprint 4 — tenant-isolated sales + online CASH checkout.
            Route::get('/sales', [SaleController::class, 'index']);
            Route::post('/sales', [SaleController::class, 'store']);
            Route::get('/sales/{sale}', [SaleController::class, 'show']);
            Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
            Route::post('/sales/{sale}/payments/cash', [SaleCashPaymentController::class, 'store']);

            // Sprint 6 — tenant-isolated receipt preview. Backend is the sole
            // authority for receipt data and print eligibility; Android only
            // formats an approved payload for ESC/POS printing.
            Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show']);

            // Sprint 5 — backend-driven QRIS: create a QRIS payment for a sale
            // and poll its status. Android never calls a payment gateway directly.
            Route::post('/sales/{sale}/payments/qris', [QrisPaymentController::class, 'store']);
            Route::get('/payments/{payment}/status', [PaymentStatusController::class, 'show']);

            // Sprint 8 — ledger-based simple inventory. Stock is derived from
            // inventory_movements (never a mutable column); SALE_OUT is created
            // by sales only. All endpoints are tenant/store isolated.
            Route::get('/inventory/current-stock', [InventoryCurrentStockController::class, 'index']);
            Route::get('/inventory/products/{product}/stock', [InventoryCurrentStockController::class, 'show']);
            Route::get('/inventory/movements', [InventoryMovementController::class, 'index']);
            Route::post('/inventory/adjustments', [InventoryAdjustmentController::class, 'store']);

            // Sprint 9 — reports & closing foundation. All figures are computed
            // by the backend report services (never trusted from the client),
            // tenant-isolated, and store-scoped. PAID sales only count as
            // revenue; pending QRIS/cancelled sales are excluded.
            Route::get('/reports/daily-sales', [DailySalesReportController::class, 'index']);
            Route::get('/reports/daily-sales/export.csv', [DailySalesCsvExportController::class, 'index']);
            Route::get('/reports/payment-summary', [PaymentSummaryReportController::class, 'index']);
            Route::get('/reports/inventory-movements-summary', [InventoryMovementSummaryController::class, 'index']);

            // Daily closing snapshot: one closing per tenant/store/business_date,
            // duplicate close replays the existing snapshot.
            Route::post('/closings/daily', [DailyClosingController::class, 'store']);
            Route::get('/closings/daily', [DailyClosingController::class, 'index']);
            Route::get('/closings/daily/{dailyClosing}', [DailyClosingController::class, 'show']);
        });
    });

    // Sprint 5 — QRIS payment gateway webhook. Unauthenticated by design; trust
    // comes from the provider signature, verified in QrisWebhookService.
    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'store']);
});
