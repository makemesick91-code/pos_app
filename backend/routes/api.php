<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\BillingAccountController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionDunningNoticeController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionDunningSummaryController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalActivityController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalCandidateController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalCandidateSummaryController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalDecisionController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalPolicyController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalReadinessController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalRiskController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalRunController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionRenewalSignoffController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionActivityController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionReadinessController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionRiskController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionSignoffController;
use App\Http\Controllers\Api\V1\Admin\BillingCollectionSummaryController;
use App\Http\Controllers\Api\V1\Admin\BillingCycleController;
use App\Http\Controllers\Api\V1\Admin\BillingInvoiceController;
use App\Http\Controllers\Api\V1\Admin\BillingInvoiceLineController;
use App\Http\Controllers\Api\V1\Admin\BillingInvoiceSummaryController;
use App\Http\Controllers\Api\V1\Admin\BillingPaymentEvidenceController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantDeviceController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\PilotDefectBurnDownController;
use App\Http\Controllers\Api\V1\Admin\PilotDefectController;
use App\Http\Controllers\Api\V1\Admin\PilotDefectEventController;
use App\Http\Controllers\Api\V1\Admin\PilotClosureController;
use App\Http\Controllers\Api\V1\Admin\PilotStabilizationReportController;
use App\Http\Controllers\Api\V1\Admin\ProductionHandoverController;
use App\Http\Controllers\Api\V1\Admin\ProductionHandoverGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\ProductionHandoverSignoffController;
use App\Http\Controllers\Api\V1\Admin\ProductionIncidentController;
use App\Http\Controllers\Api\V1\Admin\ProductionIncidentSummaryController;
use App\Http\Controllers\Api\V1\Admin\ProductionMaintenanceWindowController;
use App\Http\Controllers\Api\V1\Admin\ProductionOperationRunController;
use App\Http\Controllers\Api\V1\Admin\ProductionOpsHealthController;
use App\Http\Controllers\Api\V1\Admin\ProductionPostHandoverGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\CommercialLaunchGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\CommercialLaunchReadinessController;
use App\Http\Controllers\Api\V1\Admin\CommercialLaunchRiskController;
use App\Http\Controllers\Api\V1\Admin\CommercialLaunchRunController;
use App\Http\Controllers\Api\V1\Admin\CommercialLaunchSignoffController;
use App\Http\Controllers\Api\V1\Admin\CommercialOnboardingCapacityController;
use App\Http\Controllers\Api\V1\Admin\CommercialPackageSummaryController;
use App\Http\Controllers\Api\V1\Admin\SaasPackageCatalogController;
use App\Http\Controllers\Api\V1\Admin\LandingPageVersionController;
use App\Http\Controllers\Api\V1\Admin\LeadInterestSubmissionController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteContentSummaryController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsitePageController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteLeadSummaryController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteReadinessController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteRiskController;
use App\Http\Controllers\Api\V1\Admin\PublicWebsiteSignoffController;
use App\Http\Controllers\Api\V1\Admin\SalesLeadActivityController;
use App\Http\Controllers\Api\V1\Admin\SalesLeadAssignmentController;
use App\Http\Controllers\Api\V1\Admin\SalesLeadController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineActivitySummaryController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineGoNoGoController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineLeadSummaryController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineReadinessController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineRiskController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineSignoffController;
use App\Http\Controllers\Api\V1\Admin\SalesPipelineStageController;
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

            // Sprint 17 — Pilot Stabilization & Defect Burn-down. Platform-admin
            // controlled pilot defect register: create/list/show/update defects,
            // assign, transition status, accept risk, mark fixed, and verify
            // retests. Every lifecycle change appends an immutable defect event
            // (never deleted); accepted risk never hides the original severity.
            // Read-only burn-down and stabilization report aggregate the gate.
            Route::get('/pilot-defects', [PilotDefectController::class, 'index']);
            Route::post('/pilot-defects', [PilotDefectController::class, 'store']);
            Route::get('/pilot-defects/{defect}', [PilotDefectController::class, 'show']);
            Route::patch('/pilot-defects/{defect}', [PilotDefectController::class, 'update']);
            Route::post('/pilot-defects/{defect}/assign', [PilotDefectController::class, 'assign']);
            Route::post('/pilot-defects/{defect}/status', [PilotDefectController::class, 'status']);
            Route::post('/pilot-defects/{defect}/accept-risk', [PilotDefectController::class, 'acceptRisk']);
            Route::post('/pilot-defects/{defect}/mark-fixed', [PilotDefectController::class, 'markFixed']);
            Route::post('/pilot-defects/{defect}/verify', [PilotDefectController::class, 'verify']);
            Route::get('/pilot-defects/{defect}/events', [PilotDefectEventController::class, 'index']);

            Route::get('/pilot-defect-burndown', [PilotDefectBurnDownController::class, 'index']);
            Route::get('/pilot-stabilization-report', [PilotStabilizationReportController::class, 'index']);

            // Sprint 18 — Pilot Closure & Production Handover. Platform-admin
            // controlled: record a pilot closure run (final defect + accepted-risk
            // + stabilization review), assemble a production handover package
            // (release readiness + operator/admin + support/SLA + backup/restore +
            // ownership matrix), and collect append-only sign-off records. A
            // rejected sign-off forces NO_GO; approved-with-risk forces WATCH. The
            // read-only go/no-go endpoint aggregates every prior gate. No auto
            // production deploy, no real alert sending, no secrets exposed.
            Route::get('/pilot-closures', [PilotClosureController::class, 'index']);
            Route::post('/pilot-closures', [PilotClosureController::class, 'store']);
            Route::get('/pilot-closures/{closure}', [PilotClosureController::class, 'show']);
            Route::post('/pilot-closures/{closure}/approve', [PilotClosureController::class, 'approve']);
            Route::post('/pilot-closures/{closure}/block', [PilotClosureController::class, 'block']);

            Route::get('/production-handovers', [ProductionHandoverController::class, 'index']);
            Route::post('/production-handovers', [ProductionHandoverController::class, 'store']);
            Route::get('/production-handovers/{handover}', [ProductionHandoverController::class, 'show']);
            Route::patch('/production-handovers/{handover}', [ProductionHandoverController::class, 'update']);
            Route::post('/production-handovers/{handover}/mark-ready', [ProductionHandoverController::class, 'markReady']);
            Route::post('/production-handovers/{handover}/mark-handed-over', [ProductionHandoverController::class, 'markHandedOver']);
            Route::get('/production-handovers/{handover}/signoffs', [ProductionHandoverSignoffController::class, 'index']);
            Route::post('/production-handovers/{handover}/signoffs', [ProductionHandoverSignoffController::class, 'store']);

            Route::get('/production-handover-go-no-go', [ProductionHandoverGoNoGoController::class, 'index']);

            // Sprint 19 — Production Operations Baseline & Post-Handover
            // Governance. Platform-admin controlled: record production operation
            // runs (evidence-backed health/governance evaluation), a production
            // incident register (severity/SLA/accepted-risk aware), and
            // maintenance windows (rollback-plan aware). Read-only endpoints
            // expose ops health, the incident summary, and the aggregate
            // post-handover GO/WATCH/NO-GO. No auto production deploy, no real
            // backup/restore execution, no real alert sending, no secrets exposed.
            Route::get('/production-operation-runs', [ProductionOperationRunController::class, 'index']);
            Route::post('/production-operation-runs', [ProductionOperationRunController::class, 'store']);
            Route::get('/production-operation-runs/{operationRun}', [ProductionOperationRunController::class, 'show']);
            Route::post('/production-operation-runs/{operationRun}/approve', [ProductionOperationRunController::class, 'approve']);
            Route::post('/production-operation-runs/{operationRun}/block', [ProductionOperationRunController::class, 'block']);

            Route::get('/production-incidents', [ProductionIncidentController::class, 'index']);
            Route::post('/production-incidents', [ProductionIncidentController::class, 'store']);
            Route::get('/production-incidents/{incident}', [ProductionIncidentController::class, 'show']);
            Route::patch('/production-incidents/{incident}', [ProductionIncidentController::class, 'update']);
            Route::post('/production-incidents/{incident}/assign', [ProductionIncidentController::class, 'assign']);
            Route::post('/production-incidents/{incident}/status', [ProductionIncidentController::class, 'status']);
            Route::post('/production-incidents/{incident}/accept-risk', [ProductionIncidentController::class, 'acceptRisk']);

            Route::get('/production-maintenance-windows', [ProductionMaintenanceWindowController::class, 'index']);
            Route::post('/production-maintenance-windows', [ProductionMaintenanceWindowController::class, 'store']);
            Route::get('/production-maintenance-windows/{maintenanceWindow}', [ProductionMaintenanceWindowController::class, 'show']);
            Route::patch('/production-maintenance-windows/{maintenanceWindow}', [ProductionMaintenanceWindowController::class, 'update']);
            Route::post('/production-maintenance-windows/{maintenanceWindow}/status', [ProductionMaintenanceWindowController::class, 'status']);

            Route::get('/production-ops-health', [ProductionOpsHealthController::class, 'index']);
            Route::get('/production-incident-summary', [ProductionIncidentSummaryController::class, 'index']);
            Route::get('/production-post-handover-go-no-go', [ProductionPostHandoverGoNoGoController::class, 'index']);

            // Sprint 20 — commercial launch readiness & SaaS packaging. Platform
            // admin only. Package pricing is governance metadata only. No public
            // signup, no real billing collection, no payment gateway subscription,
            // no auto production deploy, no real alert sending, no secrets exposed.
            Route::get('/commercial-launch-runs', [CommercialLaunchRunController::class, 'index']);
            Route::post('/commercial-launch-runs', [CommercialLaunchRunController::class, 'store']);
            Route::get('/commercial-launch-runs/{launchRun}', [CommercialLaunchRunController::class, 'show']);
            Route::post('/commercial-launch-runs/{launchRun}/approve', [CommercialLaunchRunController::class, 'approve']);
            Route::post('/commercial-launch-runs/{launchRun}/block', [CommercialLaunchRunController::class, 'block']);
            Route::get('/commercial-launch-runs/{launchRun}/signoffs', [CommercialLaunchSignoffController::class, 'index']);
            Route::post('/commercial-launch-runs/{launchRun}/signoffs', [CommercialLaunchSignoffController::class, 'store']);

            Route::get('/saas-packages', [SaasPackageCatalogController::class, 'index']);
            Route::post('/saas-packages', [SaasPackageCatalogController::class, 'store']);
            Route::get('/saas-packages/{package}', [SaasPackageCatalogController::class, 'show']);
            Route::patch('/saas-packages/{package}', [SaasPackageCatalogController::class, 'update']);
            Route::post('/saas-packages/{package}/approve', [SaasPackageCatalogController::class, 'approve']);
            Route::post('/saas-packages/{package}/retire', [SaasPackageCatalogController::class, 'retire']);

            Route::get('/commercial-risks', [CommercialLaunchRiskController::class, 'index']);
            Route::post('/commercial-risks', [CommercialLaunchRiskController::class, 'store']);
            Route::get('/commercial-risks/{risk}', [CommercialLaunchRiskController::class, 'show']);
            Route::patch('/commercial-risks/{risk}', [CommercialLaunchRiskController::class, 'update']);
            Route::post('/commercial-risks/{risk}/accept-risk', [CommercialLaunchRiskController::class, 'acceptRisk']);
            Route::post('/commercial-risks/{risk}/close', [CommercialLaunchRiskController::class, 'close']);

            Route::get('/commercial-launch-readiness', [CommercialLaunchReadinessController::class, 'index']);
            Route::get('/commercial-package-summary', [CommercialPackageSummaryController::class, 'index']);
            Route::get('/commercial-onboarding-capacity', [CommercialOnboardingCapacityController::class, 'index']);
            Route::get('/commercial-launch-go-no-go', [CommercialLaunchGoNoGoController::class, 'index']);

            // Sprint 21 — public website / landing page readiness. Platform admin
            // only. Public pages/landing content are governance metadata; leads are
            // interest-only. No public self-service signup, no real billing
            // collection, no live analytics/ad pixel, no auto production deploy, no
            // real alert sending, no secrets exposed.
            Route::get('/public-website-pages', [PublicWebsitePageController::class, 'index']);
            Route::post('/public-website-pages', [PublicWebsitePageController::class, 'store']);
            Route::get('/public-website-pages/{page}', [PublicWebsitePageController::class, 'show']);
            Route::patch('/public-website-pages/{page}', [PublicWebsitePageController::class, 'update']);
            Route::post('/public-website-pages/{page}/approve', [PublicWebsitePageController::class, 'approve']);
            Route::post('/public-website-pages/{page}/publish', [PublicWebsitePageController::class, 'publish']);
            Route::post('/public-website-pages/{page}/archive', [PublicWebsitePageController::class, 'archive']);

            Route::get('/landing-page-versions', [LandingPageVersionController::class, 'index']);
            Route::post('/landing-page-versions', [LandingPageVersionController::class, 'store']);
            Route::get('/landing-page-versions/{version}', [LandingPageVersionController::class, 'show']);
            Route::patch('/landing-page-versions/{version}', [LandingPageVersionController::class, 'update']);
            Route::post('/landing-page-versions/{version}/approve', [LandingPageVersionController::class, 'approve']);
            Route::post('/landing-page-versions/{version}/publish', [LandingPageVersionController::class, 'publish']);
            Route::post('/landing-page-versions/{version}/archive', [LandingPageVersionController::class, 'archive']);

            Route::get('/lead-interest-submissions', [LeadInterestSubmissionController::class, 'index']);
            Route::get('/lead-interest-submissions/{lead}', [LeadInterestSubmissionController::class, 'show']);
            Route::post('/lead-interest-submissions/{lead}/status', [LeadInterestSubmissionController::class, 'status']);

            Route::get('/public-website-risks', [PublicWebsiteRiskController::class, 'index']);
            Route::post('/public-website-risks', [PublicWebsiteRiskController::class, 'store']);
            Route::get('/public-website-risks/{risk}', [PublicWebsiteRiskController::class, 'show']);
            Route::patch('/public-website-risks/{risk}', [PublicWebsiteRiskController::class, 'update']);
            Route::post('/public-website-risks/{risk}/accept-risk', [PublicWebsiteRiskController::class, 'acceptRisk']);
            Route::post('/public-website-risks/{risk}/close', [PublicWebsiteRiskController::class, 'close']);

            Route::get('/public-website-signoffs', [PublicWebsiteSignoffController::class, 'index']);
            Route::post('/public-website-signoffs', [PublicWebsiteSignoffController::class, 'store']);

            Route::get('/public-website-readiness', [PublicWebsiteReadinessController::class, 'index']);
            Route::get('/public-website-content-summary', [PublicWebsiteContentSummaryController::class, 'index']);
            Route::get('/public-website-lead-summary', [PublicWebsiteLeadSummaryController::class, 'index']);
            Route::get('/public-website-go-no-go', [PublicWebsiteGoNoGoController::class, 'index']);

            // Sprint 22 — lead management / sales pipeline readiness. Platform
            // admin only. Sales leads may be imported from public-website lead
            // interest submissions but NEVER auto-create a tenant/user/
            // subscription/device, NEVER bill, NEVER integrate a real CRM, and
            // NEVER send real WhatsApp/email/Slack. ready-for-onboarding means a
            // manual onboarding review only. No secrets exposed.
            Route::get('/sales-pipeline/stages', [SalesPipelineStageController::class, 'index']);
            Route::post('/sales-pipeline/stages', [SalesPipelineStageController::class, 'store']);
            Route::post('/sales-pipeline/stages/ensure-defaults', [SalesPipelineStageController::class, 'ensureDefaults']);
            Route::patch('/sales-pipeline/stages/{stage}', [SalesPipelineStageController::class, 'update']);

            Route::get('/sales-leads', [SalesLeadController::class, 'index']);
            Route::post('/sales-leads', [SalesLeadController::class, 'store']);
            Route::post('/sales-leads/import-interest/{leadInterestSubmission}', [SalesLeadController::class, 'importInterest']);
            Route::get('/sales-leads/{lead}', [SalesLeadController::class, 'show']);
            Route::patch('/sales-leads/{lead}', [SalesLeadController::class, 'update']);
            Route::post('/sales-leads/{lead}/transition', [SalesLeadController::class, 'transition']);
            Route::post('/sales-leads/{lead}/qualify', [SalesLeadController::class, 'qualify']);
            Route::post('/sales-leads/{lead}/mark-lost', [SalesLeadController::class, 'markLost']);
            Route::post('/sales-leads/{lead}/ready-for-onboarding', [SalesLeadController::class, 'readyForOnboarding']);

            Route::get('/sales-leads/{lead}/activities', [SalesLeadActivityController::class, 'index']);
            Route::post('/sales-leads/{lead}/activities', [SalesLeadActivityController::class, 'store']);
            Route::post('/sales-leads/{lead}/activities/{activity}/complete', [SalesLeadActivityController::class, 'complete']);
            Route::post('/sales-leads/{lead}/activities/{activity}/cancel', [SalesLeadActivityController::class, 'cancel']);

            Route::post('/sales-leads/{lead}/assign', [SalesLeadAssignmentController::class, 'assign']);
            Route::post('/sales-leads/{lead}/unassign', [SalesLeadAssignmentController::class, 'unassign']);

            Route::get('/sales-pipeline/risks', [SalesPipelineRiskController::class, 'index']);
            Route::post('/sales-pipeline/risks', [SalesPipelineRiskController::class, 'store']);
            Route::get('/sales-pipeline/risks/{risk}', [SalesPipelineRiskController::class, 'show']);
            Route::patch('/sales-pipeline/risks/{risk}', [SalesPipelineRiskController::class, 'update']);
            Route::post('/sales-pipeline/risks/{risk}/accept-risk', [SalesPipelineRiskController::class, 'acceptRisk']);
            Route::post('/sales-pipeline/risks/{risk}/close', [SalesPipelineRiskController::class, 'close']);

            Route::get('/sales-pipeline/signoffs', [SalesPipelineSignoffController::class, 'index']);
            Route::post('/sales-pipeline/signoffs', [SalesPipelineSignoffController::class, 'store']);

            Route::get('/sales-pipeline/readiness', [SalesPipelineReadinessController::class, 'index']);
            Route::get('/sales-pipeline/lead-summary', [SalesPipelineLeadSummaryController::class, 'index']);
            Route::get('/sales-pipeline/activity-summary', [SalesPipelineActivitySummaryController::class, 'index']);
            Route::get('/sales-pipeline/go-no-go', [SalesPipelineGoNoGoController::class, 'index']);

            // Sprint 23 — billing collection governance. Platform admin only.
            // SaaS billing collection is platform-to-tenant governance and is
            // NEVER mixed with tenant POS cashier/customer payments. Invoices
            // never trigger a payment gateway, never auto-charge, and never auto-
            // suspend a tenant; paid/remaining are only mutated through payment
            // evidence review. MANUAL_QRIS_REFERENCE and WhatsApp/email activities
            // are labels/notes only — no real gateway/message sending. No secrets
            // exposed.
            Route::get('/billing/accounts', [BillingAccountController::class, 'index']);
            Route::post('/billing/accounts', [BillingAccountController::class, 'store']);
            Route::get('/billing/accounts/{account}', [BillingAccountController::class, 'show']);
            Route::patch('/billing/accounts/{account}', [BillingAccountController::class, 'update']);

            Route::get('/billing/cycles', [BillingCycleController::class, 'index']);
            Route::post('/billing/cycles', [BillingCycleController::class, 'store']);
            Route::patch('/billing/cycles/{cycle}', [BillingCycleController::class, 'update']);
            Route::post('/billing/cycles/{cycle}/open', [BillingCycleController::class, 'open']);
            Route::post('/billing/cycles/{cycle}/lock', [BillingCycleController::class, 'lock']);
            Route::post('/billing/cycles/{cycle}/close', [BillingCycleController::class, 'close']);

            Route::get('/billing/invoices', [BillingInvoiceController::class, 'index']);
            Route::post('/billing/invoices', [BillingInvoiceController::class, 'store']);
            Route::get('/billing/invoices/{invoice}', [BillingInvoiceController::class, 'show']);
            Route::patch('/billing/invoices/{invoice}', [BillingInvoiceController::class, 'update']);
            Route::post('/billing/invoices/{invoice}/lines', [BillingInvoiceLineController::class, 'store']);
            Route::patch('/billing/invoices/{invoice}/lines/{line}', [BillingInvoiceLineController::class, 'update']);
            Route::post('/billing/invoices/{invoice}/issue', [BillingInvoiceController::class, 'issue']);
            Route::post('/billing/invoices/{invoice}/mark-overdue', [BillingInvoiceController::class, 'markOverdue']);
            Route::post('/billing/invoices/{invoice}/mark-disputed', [BillingInvoiceController::class, 'markDisputed']);
            Route::post('/billing/invoices/{invoice}/void', [BillingInvoiceController::class, 'void']);

            Route::get('/billing/invoices/{invoice}/payment-evidences', [BillingPaymentEvidenceController::class, 'index']);
            Route::post('/billing/invoices/{invoice}/payment-evidences', [BillingPaymentEvidenceController::class, 'store']);
            Route::post('/billing/payment-evidences/{paymentEvidence}/under-review', [BillingPaymentEvidenceController::class, 'underReview']);
            Route::post('/billing/payment-evidences/{paymentEvidence}/accept', [BillingPaymentEvidenceController::class, 'accept']);
            Route::post('/billing/payment-evidences/{paymentEvidence}/reject', [BillingPaymentEvidenceController::class, 'reject']);
            Route::post('/billing/payment-evidences/{paymentEvidence}/void', [BillingPaymentEvidenceController::class, 'void']);

            Route::get('/billing/activities', [BillingCollectionActivityController::class, 'index']);
            Route::post('/billing/activities', [BillingCollectionActivityController::class, 'store']);
            Route::post('/billing/activities/{activity}/complete', [BillingCollectionActivityController::class, 'complete']);
            Route::post('/billing/activities/{activity}/cancel', [BillingCollectionActivityController::class, 'cancel']);

            Route::get('/billing/risks', [BillingCollectionRiskController::class, 'index']);
            Route::post('/billing/risks', [BillingCollectionRiskController::class, 'store']);
            Route::get('/billing/risks/{risk}', [BillingCollectionRiskController::class, 'show']);
            Route::patch('/billing/risks/{risk}', [BillingCollectionRiskController::class, 'update']);
            Route::post('/billing/risks/{risk}/accept-risk', [BillingCollectionRiskController::class, 'acceptRisk']);
            Route::post('/billing/risks/{risk}/close', [BillingCollectionRiskController::class, 'close']);

            Route::get('/billing/signoffs', [BillingCollectionSignoffController::class, 'index']);
            Route::post('/billing/signoffs', [BillingCollectionSignoffController::class, 'store']);

            Route::get('/billing/readiness', [BillingCollectionReadinessController::class, 'index']);
            Route::get('/billing/invoice-summary', [BillingInvoiceSummaryController::class, 'index']);
            Route::get('/billing/collection-summary', [BillingCollectionSummaryController::class, 'index']);
            Route::get('/billing/go-no-go', [BillingCollectionGoNoGoController::class, 'index']);

            // Sprint 24 — subscription renewal & dunning governance. Platform admin
            // only. Subscription renewal/dunning is lifecycle governance over
            // TenantSubscription; it is NEVER mixed with tenant POS cashier/customer
            // payments and is distinct from Sprint 23 billing collection. Nothing here
            // calls a payment gateway, auto-charges, auto-suspends/reactivates a
            // tenant, auto-renews a subscription, changes a plan/device limit, or
            // sends a real email/WhatsApp/SMS/Slack. Dunning notices are a MANUAL
            // reminder queue only. The only subscription-mutating path is the explicit
            // apply-manual-renewal action; it is audit-logged and never automatic.
            Route::get('/subscription-renewal/policies', [SubscriptionRenewalPolicyController::class, 'index']);
            Route::post('/subscription-renewal/policies', [SubscriptionRenewalPolicyController::class, 'store']);
            Route::post('/subscription-renewal/policies/ensure-default', [SubscriptionRenewalPolicyController::class, 'ensureDefault']);
            Route::get('/subscription-renewal/policies/{policy}', [SubscriptionRenewalPolicyController::class, 'show']);
            Route::patch('/subscription-renewal/policies/{policy}', [SubscriptionRenewalPolicyController::class, 'update']);

            Route::get('/subscription-renewal/runs', [SubscriptionRenewalRunController::class, 'index']);
            Route::post('/subscription-renewal/runs', [SubscriptionRenewalRunController::class, 'store']);
            Route::get('/subscription-renewal/runs/{run}', [SubscriptionRenewalRunController::class, 'show']);
            Route::post('/subscription-renewal/runs/{run}/evaluate', [SubscriptionRenewalRunController::class, 'evaluate']);
            Route::post('/subscription-renewal/runs/{run}/complete', [SubscriptionRenewalRunController::class, 'complete']);

            Route::get('/subscription-renewal/candidates', [SubscriptionRenewalCandidateController::class, 'index']);
            Route::get('/subscription-renewal/candidates/{candidate}', [SubscriptionRenewalCandidateController::class, 'show']);
            Route::patch('/subscription-renewal/candidates/{candidate}', [SubscriptionRenewalCandidateController::class, 'update']);
            Route::post('/subscription-renewal/candidates/{candidate}/ready-for-manual-renewal', [SubscriptionRenewalCandidateController::class, 'readyForManualRenewal']);
            Route::post('/subscription-renewal/candidates/{candidate}/grace-review', [SubscriptionRenewalCandidateController::class, 'graceReview']);
            Route::post('/subscription-renewal/candidates/{candidate}/overdue-review', [SubscriptionRenewalCandidateController::class, 'overdueReview']);
            Route::post('/subscription-renewal/candidates/{candidate}/do-not-renew', [SubscriptionRenewalCandidateController::class, 'doNotRenew']);

            Route::get('/subscription-renewal/candidates/{candidate}/dunning-notices', [SubscriptionDunningNoticeController::class, 'index']);
            Route::post('/subscription-renewal/candidates/{candidate}/dunning-notices', [SubscriptionDunningNoticeController::class, 'store']);
            Route::post('/subscription-renewal/dunning-notices/{notice}/prepare', [SubscriptionDunningNoticeController::class, 'prepare']);
            Route::post('/subscription-renewal/dunning-notices/{notice}/mark-sent-manually', [SubscriptionDunningNoticeController::class, 'markSentManually']);
            Route::post('/subscription-renewal/dunning-notices/{notice}/complete', [SubscriptionDunningNoticeController::class, 'complete']);
            Route::post('/subscription-renewal/dunning-notices/{notice}/cancel', [SubscriptionDunningNoticeController::class, 'cancel']);
            Route::post('/subscription-renewal/dunning-notices/{notice}/skip', [SubscriptionDunningNoticeController::class, 'skip']);

            Route::get('/subscription-renewal/candidates/{candidate}/decisions', [SubscriptionRenewalDecisionController::class, 'index']);
            Route::post('/subscription-renewal/candidates/{candidate}/decisions', [SubscriptionRenewalDecisionController::class, 'store']);
            Route::post('/subscription-renewal/decisions/{decision}/void', [SubscriptionRenewalDecisionController::class, 'void']);
            Route::post('/subscription-renewal/decisions/{decision}/apply-manual-renewal', [SubscriptionRenewalDecisionController::class, 'applyManualRenewal']);

            Route::get('/subscription-renewal/activities', [SubscriptionRenewalActivityController::class, 'index']);
            Route::post('/subscription-renewal/activities', [SubscriptionRenewalActivityController::class, 'store']);
            Route::post('/subscription-renewal/activities/{activity}/complete', [SubscriptionRenewalActivityController::class, 'complete']);
            Route::post('/subscription-renewal/activities/{activity}/cancel', [SubscriptionRenewalActivityController::class, 'cancel']);

            Route::get('/subscription-renewal/risks', [SubscriptionRenewalRiskController::class, 'index']);
            Route::post('/subscription-renewal/risks', [SubscriptionRenewalRiskController::class, 'store']);
            Route::get('/subscription-renewal/risks/{risk}', [SubscriptionRenewalRiskController::class, 'show']);
            Route::patch('/subscription-renewal/risks/{risk}', [SubscriptionRenewalRiskController::class, 'update']);
            Route::post('/subscription-renewal/risks/{risk}/accept-risk', [SubscriptionRenewalRiskController::class, 'acceptRisk']);
            Route::post('/subscription-renewal/risks/{risk}/close', [SubscriptionRenewalRiskController::class, 'close']);

            Route::get('/subscription-renewal/signoffs', [SubscriptionRenewalSignoffController::class, 'index']);
            Route::post('/subscription-renewal/signoffs', [SubscriptionRenewalSignoffController::class, 'store']);

            Route::get('/subscription-renewal/readiness', [SubscriptionRenewalReadinessController::class, 'index']);
            Route::get('/subscription-renewal/candidate-summary', [SubscriptionRenewalCandidateSummaryController::class, 'index']);
            Route::get('/subscription-renewal/dunning-summary', [SubscriptionDunningSummaryController::class, 'index']);
            Route::get('/subscription-renewal/go-no-go', [SubscriptionRenewalGoNoGoController::class, 'index']);
        });
    });

    // Sprint 5 — QRIS payment gateway webhook. Unauthenticated by design; trust
    // comes from the provider signature, verified in QrisWebhookService.
    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'store']);
});
