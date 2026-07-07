<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantDeviceController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\TenantDemoDataController;
use App\Http\Controllers\Api\V1\Admin\TenantOnboardingController;
use App\Http\Controllers\Api\V1\Admin\TenantOnboardingStatusController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceHeartbeatController;
use App\Http\Controllers\Api\V1\RegisteredDeviceController;
use App\Http\Controllers\Api\V1\SubscriptionStatusController;
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

            // Sprint 10 — subscription & device management. These endpoints are
            // NOT wrapped by subscription.active / device.registered so a tenant
            // can always read its (possibly blocked) status and register/revoke a
            // device to unblock. Tenant/device ownership is still enforced inside
            // each controller/service — a tenant can never touch another tenant's
            // subscription or devices.
            Route::get('/subscription/status', [SubscriptionStatusController::class, 'show']);
            Route::post('/devices/register', [RegisteredDeviceController::class, 'store']);
            Route::post('/devices/heartbeat', [DeviceHeartbeatController::class, 'store']);
            Route::get('/devices', [RegisteredDeviceController::class, 'index']);
            Route::post('/devices/{device}/revoke', [RegisteredDeviceController::class, 'revoke']);

            // Sprint 10 — protected business APIs. Beyond an active user/tenant,
            // the tenant subscription must be allowed (backend-computed) AND the
            // request must come from an ACTIVE registered device (X-Device-UUID).
            // Expired/cancelled/suspended subscriptions or missing/revoked devices
            // are blocked here; auth + subscription status + device management
            // above remain reachable.
            Route::middleware(['subscription.active', 'device.registered'])->group(function () {
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

        // Sprint 11 — Admin SaaS Control Panel. Platform-admin-only, cross-tenant
        // administration. These routes are deliberately NOT wrapped by
        // tenant.active / tenant.context / subscription.active / device.registered:
        // the platform admin carries no tenant context and reads cross-tenant data
        // only through admin services. Tenant business users are blocked by
        // platform.admin. No impersonation, no real billing, no tenant hard-delete.
        Route::prefix('admin')->middleware('platform.admin')->group(function () {
            Route::get('/tenants', [AdminTenantController::class, 'index']);
            Route::get('/tenants/{tenant}', [AdminTenantController::class, 'show']);

            Route::get('/tenants/{tenant}/subscriptions', [AdminTenantSubscriptionController::class, 'index']);
            Route::post('/tenants/{tenant}/subscriptions', [AdminTenantSubscriptionController::class, 'store']);
            Route::patch('/tenants/{tenant}/subscriptions/{subscription}', [AdminTenantSubscriptionController::class, 'update']);

            Route::get('/tenants/{tenant}/devices', [AdminTenantDeviceController::class, 'index']);
            Route::post('/tenants/{tenant}/devices/{device}/revoke', [AdminTenantDeviceController::class, 'revoke']);

            Route::get('/subscription-plans', [AdminSubscriptionPlanController::class, 'index']);
            Route::post('/subscription-plans', [AdminSubscriptionPlanController::class, 'store']);
            Route::patch('/subscription-plans/{plan}', [AdminSubscriptionPlanController::class, 'update']);
            Route::post('/subscription-plans/{plan}/deactivate', [AdminSubscriptionPlanController::class, 'deactivate']);

            Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
            Route::get('/audit-logs/{auditLog}', [AdminAuditLogController::class, 'show']);

            // Sprint 12 — Tenant Onboarding & Demo Data Foundation. Platform-admin
            // controlled: create a tenant + default store + owner user +
            // subscription (transaction-safe, idempotent by onboarding_reference)
            // and optionally seed tenant-owned demo data. Demo reset is guarded by
            // confirm_demo_reset and only removes onboarding-seeded demo data. No
            // public signup, no real billing, no invites, no impersonation.
            Route::post('/tenant-onboarding', [TenantOnboardingController::class, 'store']);
            Route::get('/tenant-onboarding', [TenantOnboardingController::class, 'index']);
            Route::get('/tenant-onboarding/{onboardingRun}', [TenantOnboardingController::class, 'show']);

            Route::get('/tenants/{tenant}/onboarding-status', [TenantOnboardingStatusController::class, 'show']);
            Route::post('/tenants/{tenant}/demo-data', [TenantDemoDataController::class, 'store']);
            Route::post('/tenants/{tenant}/demo-data/reset', [TenantDemoDataController::class, 'reset']);
        });
    });

    // Sprint 5 — QRIS payment gateway webhook. Unauthenticated by design; trust
    // comes from the provider signature, verified in QrisWebhookService.
    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'store']);
});
