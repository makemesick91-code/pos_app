# Project Rules

The canonical source of truth for this project is:

`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`

No implementation may contradict this document unless the foundation document is explicitly updated first.

Mandatory rules:

1. This project is a multi-tenant Android POS SaaS, not a single-store POS.
2. All operational data must be tenant-isolated.
3. QRIS must be dynamic and backend-driven.
4. Payment gateway credentials must never exist in Android code.
5. Cash transaction may work offline.
6. QRIS transaction requires online connectivity.
7. Android must remain lightweight for older devices.
8. Subscription and device limit must be part of the SaaS foundation.
9. Every sprint must reference this foundation document.
10. Docs-only output is not accepted for implementation sprints unless explicitly requested.

## Sprint Execution Rule

Every sprint must:

1. Reference `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`.
2. Produce validation evidence in `docs/sprints/`.
3. Include clear GO / NO-GO criteria.
4. Avoid implementation that contradicts the foundation.
5. Avoid docs-only output for implementation sprints unless explicitly requested.

## Foundation Lock Index

This project is governed by:

1. `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
2. `docs/sprints/sprint-0-project-setup.md`
3. `docs/sprints/sprint-1-saas-tenant-foundation.md`
4. `docs/sprints/sprint-2-product-foundation.md`
5. `docs/sprints/sprint-3-android-cashier-foundation.md`
6. `docs/sprints/sprint-4-sales-backend-integration.md`
7. `docs/sprints/sprint-5-qris-payment-gateway-foundation.md`
8. `docs/sprints/sprint-6-printer-receipt-foundation.md`
9. `docs/sprints/sprint-7-offline-cash-sync-foundation.md`
10. `docs/sprints/sprint-8-inventory-simple-foundation.md`
11. `docs/sprints/sprint-9-reports-closing-foundation.md`
12. `docs/sprints/sprint-10-subscription-device-limit-foundation.md`
13. `docs/sprints/sprint-11-admin-saas-control-panel-foundation.md`
14. `docs/sprints/sprint-12-tenant-onboarding-demo-data-foundation.md`

No sprint may contradict these documents unless the canonical foundation is explicitly updated first.

## Sprint 0 Runtime Rule

Sprint 0 established the controlled monorepo structure:

- `backend/` for Laravel API
- `android/` for native Android Kotlin
- `docs/` for foundation and sprint evidence
- `scripts/` for validation smoke scripts
- `.github/workflows/` for CI

Future work must preserve this structure unless the foundation is updated.

## Sprint 1 Multi-Tenant Runtime Rule

Starting Sprint 1, backend runtime implementation must enforce tenant isolation.

Mandatory:

1. Tenant-owned data must include `tenant_id`.
2. Store-owned data must include both `tenant_id` and `store_id` where applicable.
3. Tenant context must come from authenticated user/session context, not arbitrary client input.
4. Any client-provided store selector must be validated against the authenticated user tenant.
5. Tests must prove that tenant A cannot access tenant B data.

## Sprint 2 Product Foundation Runtime Rule

Starting Sprint 2, product catalog runtime implementation must enforce tenant isolation and Android sync readiness.

Mandatory:

1. Product categories must be tenant-owned.
2. Products must be tenant-owned.
3. Store-specific product price overrides must be tenant-owned and store-scoped.
4. Product category and product APIs must use authenticated tenant context.
5. Client input may not assign arbitrary `tenant_id`.
6. Any `store_id` input must be validated against the authenticated tenant.
7. Tenant A must never read, create, update, delete, or sync product data from tenant B.
8. Product sync endpoints must support incremental sync using `updated_since`.
9. Product sync output must be lightweight for older Android devices.
10. Product foundation must not implement sales, payment, QRIS, printer, or inventory movement runtime yet.

## Sprint 3 Android Cashier Foundation Runtime Rule

Starting Sprint 3, Android cashier implementation must remain lightweight, native, and sync-ready.

Mandatory:

1. Android implementation must use native Kotlin and native Android Views/XML for cashier foundation.
2. Android must keep `minSdk = 26` and `targetSdk = 35`.
3. Android package must remain `com.aishtech.poslite`.
4. Android must never store payment gateway credentials.
5. Android must not call payment gateway APIs directly.
6. Android login must consume backend `/api/v1/auth/login`.
7. Android session/token handling must not store user password.
8. Product and category catalog must be cached locally using Room SQLite.
9. Product search must run locally against cached catalog data.
10. Product sync must consume `/api/v1/sync/products` and `/api/v1/sync/categories`.
11. Product sync must support incremental `updated_since`.
12. Cart foundation may remain local-only in Sprint 3.
13. Sprint 3 must not implement sales submission, QRIS, webhook, printer, or inventory movement runtime.
14. Android UI must remain lightweight for older HP Android devices.
15. Android implementation must include validation evidence and smoke checks.

## Sprint 4 Sales Backend Integration Runtime Rule

Starting Sprint 4, sales and cash checkout implementation must enforce tenant isolation, price snapshotting, and online cash finalization.

Mandatory:

1. Sales must be tenant-owned.
2. Sales must be store-owned where applicable.
3. Sale items must be tenant-owned and store-owned.
4. Payments must be tenant-owned and store-owned.
5. Android may submit cart only to backend `/api/v1/sales` or approved sales endpoints.
6. Client input may not assign arbitrary `tenant_id`.
7. Client input may not assign arbitrary sale totals as source of truth.
8. Backend must recalculate subtotal, discount, grand total, paid total, and change total.
9. Backend must snapshot product name and unit price into `sale_items`.
10. Product IDs in checkout must belong to authenticated tenant.
11. Store context must come from authenticated tenant context and validated store selection.
12. Tenant A must never read, create, cancel, or pay sales for tenant B.
13. Cash payment finalization must not use QRIS/payment gateway logic.
14. QRIS, payment webhook, printer, offline sales sync, and inventory movement runtime are not part of Sprint 4.
15. Android cart must clear only after successful backend sale creation/payment.
16. Android cart must remain intact if backend checkout fails.
17. Sales endpoints must be covered by tenant isolation tests.

## Sprint 5 QRIS Payment Gateway Foundation Runtime Rule

Starting Sprint 5, QRIS implementation must be backend-driven, secure, tenant-isolated, and webhook-ready.

Mandatory:

1. Android must never store payment gateway credentials.
2. Android must never call payment gateway APIs directly.
3. Android may only request QRIS through backend-approved endpoints.
4. QRIS payment creation must be tied to an authenticated tenant-owned sale.
5. Tenant A must never create, view, update, or receive webhook effects for tenant B payments.
6. Payment gateway credentials must only live in backend environment/config.
7. Tests must not depend on external payment gateway network calls.
8. A fake/sandbox QRIS provider must be available for local/testing.
9. QRIS payments must support statuses: PENDING, PAID, FAILED, EXPIRED, CANCELLED.
10. QRIS webhook processing must be idempotent.
11. Webhook payloads must be logged in `payment_webhook_logs`.
12. Webhook signature validation foundation must exist.
13. Payment status updates must update the related sale payment status consistently.
14. QRIS must be online-only.
15. Cash payment behavior from Sprint 4 must remain intact.
16. Sprint 5 must not implement payout, refund, printer, offline QRIS, offline sales sync, or inventory movement runtime.
17. Payment reconciliation command foundation must exist.

## Sprint 6 Printer & Receipt Foundation Runtime Rule

Starting Sprint 6, receipt and printer implementation must be tenant-isolated, payment-aware, Android-native, and lightweight.

Mandatory:

1. Receipt data must be generated by backend from tenant-owned sales.
2. Android must not calculate authoritative receipt totals.
3. Android may format receipt for printing only from backend-approved receipt data.
4. Tenant A must never view, preview, or print receipt data from tenant B.
5. Receipt for CASH sale may be final only when sale payment_status is PAID.
6. Receipt for QRIS sale may be final only when QRIS payment status and sale payment_status are PAID.
7. Pending, unpaid, expired, failed, cancelled, or tenant-invalid sales must not produce final printable receipt.
8. Receipt item names, unit prices, quantities, discounts, and totals must come from sale item snapshots.
9. ESC/POS formatting must be local Android formatting and must not require heavy printer SDK.
10. Bluetooth printer foundation must be Android-native and lightweight.
11. Printer settings must be stored locally on Android and must not contain payment credentials.
12. Android must not store payment gateway credentials.
13. Sprint 6 must preserve cash payment and QRIS payment behavior from Sprint 4 and Sprint 5.
14. Sprint 6 must not implement live QRIS activation, payout, refund, offline sales sync, or inventory movement runtime.
15. Gradle wrapper must exist in the repository from Sprint 6 onward.
16. Android build CI must run assembleDebug and unit tests from Sprint 6 onward.
17. GO tag is forbidden if Android build CI is not active and green.

## Sprint 7 Offline Cash & Sync Foundation Runtime Rule

Starting Sprint 7, offline operation must be cash-only, idempotent, lightweight, and sync-safe.

Mandatory:

1. Offline transaction support is limited to CASH sales only.
2. QRIS must remain online-only.
3. Android must not create QRIS payment while offline.
4. Android must store offline CASH sales locally before clearing the cart.
5. Android must keep the cart if local offline save fails.
6. Offline sales must have a client-generated reference/idempotency key.
7. Backend sales API must handle duplicate offline submit idempotently.
8. Backend must not create duplicate sales for the same tenant/store/client reference.
9. Backend must continue recalculating totals and must not trust Android totals.
10. Backend must continue snapshotting sale item product name and price.
11. Sync must use authenticated tenant context.
12. Tenant A must never sync or resolve offline sale references for tenant B.
13. Android sync queue must preserve failed/pending offline sales until synced or explicitly resolved.
14. WorkManager sync must use retry/backoff and must not crash the app.
15. Offline receipt must be clearly marked as draft/offline until server sync succeeds.
16. Final receipt rules from Sprint 6 must remain payment-aware.
17. Cash, QRIS, receipt, and printer behavior from previous sprints must remain intact.
18. Android Gradle wrapper must remain committed.
19. Android CI must continue running assembleDebug and testDebugUnitTest.
20. Sprint 7 must not implement offline QRIS, inventory movement runtime, advanced reports, or owner dashboard.

## Sprint 8 Inventory Simple Foundation Runtime Rule

Starting Sprint 8, inventory implementation must be ledger-based, tenant-isolated, store-scoped, and idempotency-safe.

Mandatory:

1. Stock must be calculated from `inventory_movements`.
2. Mutable product stock columns must not be used as source of truth.
3. Inventory movements must be tenant-owned.
4. Inventory movements must be store-owned.
5. Inventory movements must be product-owned.
6. Client input may not assign arbitrary `tenant_id`.
7. Any `store_id` input must be validated against authenticated tenant context.
8. Any `product_id` input must belong to authenticated tenant.
9. SALE_OUT movement must be generated from successful sales for stock-tracked products.
10. SALE_OUT movement must use sale item snapshots and backend sale data, not Android totals.
11. Idempotent offline sale replay must not create duplicate SALE_OUT movements.
12. Adjustment movements must be explicit and auditable.
13. Current stock endpoint must calculate stock from movement sums.
14. Tenant A must never read or write inventory movement/stock data for tenant B.
15. QRIS, cash, receipt, printer, and offline sync behavior from previous sprints must remain intact.
16. Sprint 8 must not implement procurement, stock transfer, stock opname, batch/lot, valuation, or advanced inventory reports.
17. Android stock visibility must remain lightweight and must not load heavy reports.
18. Android CI must continue running assembleDebug and testDebugUnitTest.

## Sprint 9 Reports & Closing Foundation Runtime Rule

Starting Sprint 9, reporting and closing implementation must be tenant-isolated, store-scoped, payment-aware, and lightweight.

Mandatory:

1. Reports must be generated from backend authoritative data.
2. Android must not calculate authoritative report totals.
3. Sales revenue reports must count PAID sales only.
4. Pending QRIS payments must not be counted as paid revenue.
5. Cancelled sales must not be counted as paid revenue, but may be counted separately.
6. Offline cash sales count only after synced to backend.
7. Inventory summary must be generated from `inventory_movements`, not mutable product stock.
8. Closing snapshot must be tenant-owned.
9. Closing snapshot must be store-owned.
10. Closing business date must be explicit.
11. Only one closing snapshot may exist per tenant/store/business_date.
12. Duplicate closing request must not create duplicate closing snapshots.
13. Closing totals must come from backend report services, not client-provided totals.
14. Tenant A must never view, export, create, or close reports for tenant B.
15. CSV export must be tenant-isolated and must not expose secrets/raw gateway payloads.
16. Android report UI must remain lightweight and summary-focused.
17. Sprint 9 must not implement advanced BI dashboard, accounting journal, PDF/Excel export, tax reporting complex, stock valuation, or procurement reports.
18. Cash, QRIS, receipt, printer, offline sync, and inventory behavior from previous sprints must remain intact.

## Sprint 10 Subscription & Device Limit Foundation Runtime Rule

Starting Sprint 10, subscription and device access must be tenant-owned, backend-enforced, and Android-aware.

Mandatory:

1. Subscription plans must be backend-owned.
2. Tenant subscriptions must be tenant-owned.
3. Registered devices must be tenant-owned.
4. Client input may not assign arbitrary `tenant_id`.
5. Android device registration must happen through backend-approved endpoints.
6. Android must not bypass subscription/device checks.
7. Active subscription status must be checked by backend, not trusted from Android.
8. Device limit must be enforced by backend.
9. Registered device identity must be generated and stored locally on Android.
10. Device identity must not contain payment gateway credentials or user password.
11. Device registration must be tied to authenticated tenant/user context.
12. Tenant A must never view, register, revoke, or consume device slots from tenant B.
13. Expired/cancelled/suspended subscriptions must block protected business APIs except explicitly allowed status/auth endpoints.
14. Grace period may allow limited access only if backend marks it allowed.
15. Offline cash from Sprint 7 must remain usable only within allowed subscription/device policy; if policy is expired, Android must surface blocked state once status is known.
16. QRIS, cash, receipt, printer, inventory, reports, and closing behavior from previous sprints must remain intact.
17. Sprint 10 must not implement real billing charge collection, Play Billing, proration, reseller portal, or advanced billing analytics.
18. Android CI must continue running assembleDebug and testDebugUnitTest.
19. Android CI must continue running assembleDebug and testDebugUnitTest.

## Sprint 11 Admin SaaS Control Panel Foundation Runtime Rule

Starting Sprint 11, platform administration must be backend-authorized, audit-logged, and separated from tenant business APIs.

Mandatory:

1. Admin SaaS APIs must require authenticated platform admin authorization.
2. Tenant users must never access admin SaaS APIs.
3. Platform admin authorization must be backend-enforced.
4. Platform admin APIs must be separated under an admin route namespace.
5. Admin tenant list/detail must not expose secrets or raw payment gateway payloads.
6. Admin subscription assignment/update must be audit-logged.
7. Admin device revoke actions must be audit-logged.
8. Admin plan create/update/deactivate actions must be audit-logged.
9. Admin audit logs must record actor, action, target type, target id, tenant context when available, and timestamp.
10. Admin APIs may manage subscription/device foundation but must not implement real billing charge collection.
11. Admin APIs must not hard-delete tenants in Sprint 11.
12. Admin APIs must not implement impersonation or login-as-tenant in Sprint 11.
13. Android app must not contain admin SaaS control panel UI.
14. Android app must continue normal tenant/business behavior from previous sprints.
15. Existing subscription/device enforcement from Sprint 10 must remain intact.
16. Cash, QRIS, receipt, printer, offline sync, inventory, reports, and closing behavior from previous sprints must remain intact.
17. Sprint 11 must not implement Play Billing, proration, reseller portal, advanced admin analytics, or full web dashboard UI.
18. Android CI must continue running assembleDebug and testDebugUnitTest.

## Sprint 12 Tenant Onboarding & Demo Data Foundation Runtime Rule

Starting Sprint 12, tenant onboarding and demo data must be platform-admin-controlled, transaction-safe, tenant-isolated, and audit-logged.

Mandatory:

1. Tenant onboarding must be executed only through platform admin authorization.
2. Tenant users must never create tenants through onboarding APIs.
3. Public self-service signup is not part of Sprint 12.
4. Onboarding must create tenant, default store, owner/admin tenant user, and initial subscription in a transaction.
5. Onboarding must be idempotent through a backend-approved onboarding reference.
6. Duplicate onboarding request with the same onboarding reference must not create duplicate tenants/users/stores/subscriptions.
7. Demo data must be tenant-owned.
8. Demo products, prices, inventory movements, sales, payments, reports, and closings must never leak across tenants.
9. Demo opening stock must use inventory movements, not mutable stock columns.
10. Demo sales must use existing sales/payment services where practical and must not bypass subscription/device/inventory/report rules unsafely.
11. Demo reset must be guarded and must not delete production tenant data unless explicitly marked demo and allowed.
12. Demo reset must be audit-logged.
13. Onboarding actions must be audit-logged.
14. Onboarding status/checklist must be backend-generated, not trusted from client.
15. Admin onboarding APIs must not implement real billing charge collection.
16. Admin onboarding APIs must not send real email/WhatsApp invites in Sprint 12.
17. Admin onboarding APIs must not implement tenant impersonation.
18. Android app must not contain onboarding/admin SaaS control panel UI.
19. Android app must continue normal tenant/business behavior from previous sprints.
20. Existing platform admin, subscription/device enforcement, cash, QRIS, receipt, printer, offline sync, inventory, reports, and closing behavior from previous sprints must remain intact.
21. Android CI must continue running assembleDebug and testDebugUnitTest.
