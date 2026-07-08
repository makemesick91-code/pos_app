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
15. `docs/sprints/sprint-13-production-readiness-release-hardening-foundation.md`
16. `docs/sprints/sprint-14-pilot-release-candidate-operator-uat-foundation.md`
17. `docs/sprints/sprint-15-pilot-deployment-field-trial-evidence-foundation.md`
18. `docs/sprints/sprint-16-pilot-monitoring-hypercare-foundation.md`
19. `docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md`
20. `docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md`
21. `docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md`
22. `docs/sprints/sprint-20-commercial-launch-readiness-saas-packaging-foundation.md`
23. `docs/sprints/sprint-21-public-website-landing-page-readiness-foundation.md`
24. `docs/sprints/sprint-22-lead-management-sales-pipeline-readiness-foundation.md`
25. `docs/sprints/sprint-23-billing-collection-governance-foundation.md`
26. `docs/sprints/sprint-24-subscription-renewal-dunning-governance-foundation.md`
27. `docs/sprints/sprint-25-tenant-lifecycle-enforcement-manual-suspension-governance-foundation.md`

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

## Sprint 13 Production Readiness & Release Hardening Foundation Runtime Rule

Starting Sprint 13, releases must pass backend, Android, environment, security, and evidence gates before a GO tag is created.

Mandatory:

1. GO tag must not be created unless release readiness checks pass.
2. Backend tests must pass before GO tag.
3. Android CI assembleDebug must pass before GO tag.
4. Android CI testDebugUnitTest must pass before GO tag.
5. Production readiness checks must validate APP_ENV/APP_DEBUG/APP_KEY/database/cache/session/queue/storage assumptions without exposing secrets.
6. Release checks must not print secret values.
7. Release checks must fail when dangerous production settings are detected.
8. Migration readiness must be checked before release GO.
9. Backup/restore runbook foundation must exist before release GO.
10. CI release gate must run smoke, backend tests, and Android build/test.
11. Android version/package/minSdk/targetSdk governance must remain enforced.
12. No app signing key, production credential, payment gateway secret, or `.env` file may be committed.
13. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, and closing behavior from previous sprints must remain intact.
14. Sprint 13 must not implement new business features.
15. Sprint 13 must not deploy to production automatically unless explicitly approved and fully evidenced.
16. Release evidence must be stored in sprint documentation.
17. Working tree must be clean before GO tag.
18. Android CI remains the authoritative build gate if local Android build cannot run.

## Sprint 14 Pilot Release Candidate & Operator UAT Foundation Runtime Rule

Starting Sprint 14, pilot release candidates must pass RC, UAT, smoke,
issue-register, backend, Android, and release-readiness evidence gates before a
GO tag is created.

Mandatory:

1. Pilot RC must not be declared GO without backend tests passing.
2. Pilot RC must not be declared GO without Android CI assembleDebug passing.
3. Pilot RC must not be declared GO without Android CI testDebugUnitTest passing.
4. Pilot RC must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Pilot RC must include an operator UAT checklist.
6. Pilot RC must include a smoke scenario pack covering login, sync, cashier, cash sale, QRIS status, receipt, printer, offline cash, inventory, reports, closing, subscription/device gate, and admin onboarding.
7. Pilot RC must include an issue register foundation.
8. RC decision must be GO/WATCH/NO-GO and evidence-backed.
9. UAT evidence must not contain real passwords, real payment gateway secrets, production customer data, or private credentials.
10. Operator UAT artifacts must use placeholders or demo tenant data only.
11. Pilot issue register must classify severity and blocking status.
12. Critical/blocker open issues must force NO-GO unless explicitly documented as outside scope and accepted.
13. WATCH decision requires clear risk notes and follow-up actions.
14. Sprint 14 must not implement new business features.
15. Sprint 14 must not perform automatic production deployment.
16. Sprint 14 must not commit signing keys, APK/AAB, `.env`, database dumps, or secrets.
17. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, and release hardening behavior from previous sprints must remain intact.
18. Android CI remains the authoritative build gate if local Android build cannot run.

## Sprint 15 Pilot Deployment & Field Trial Evidence Foundation Runtime Rule

Starting Sprint 15, pilot deployment and field trial readiness must be
evidence-backed, non-destructive, secret-safe, and gated before a GO tag is
created.

Mandatory:

1. Pilot deployment must not be declared GO without backend tests passing.
2. Pilot deployment must not be declared GO without Android CI assembleDebug passing.
3. Pilot deployment must not be declared GO without Android CI testDebugUnitTest passing.
4. Pilot deployment must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Pilot deployment must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Pilot deployment evidence must include a backend deployment dry-run checklist.
7. Pilot deployment evidence must include an Android RC artifact handling checklist.
8. Pilot deployment evidence must include an operator device readiness checklist.
9. Field trial evidence must include a post-deploy smoke checklist.
10. Field trial evidence must include a rollback checklist.
11. Field trial evidence must include a daily monitoring checklist.
12. Field issue register must classify severity and blocking status.
13. BLOCKER/CRITICAL open field issues must force NO-GO unless explicitly accepted as outside scope with documented risk.
14. Field trial GO/WATCH/NO-GO report must be evidence-backed.
15. Pilot artifacts must not contain real passwords, real payment gateway secrets, production customer data, or private credentials.
16. Android APK/AAB/signing key/keystore must not be committed.
17. Sprint 15 must not implement new business features.
18. Sprint 15 must not perform automatic production deployment unless explicitly configured, approved, and evidenced.
19. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, and UAT behavior from previous sprints must remain intact.
20. Android CI remains the authoritative build gate if local Android build cannot run.

## Sprint 16 Pilot Monitoring & Hypercare Foundation Runtime Rule

Starting Sprint 16, pilot monitoring and hypercare must be evidence-backed,
severity-driven, secret-safe, tenant-aware, and gated before a GO tag is created.

Mandatory:

1. Pilot monitoring must not be declared GO without backend tests passing.
2. Pilot monitoring must not be declared GO without Android CI assembleDebug passing.
3. Pilot monitoring must not be declared GO without Android CI testDebugUnitTest passing.
4. Pilot monitoring must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Pilot monitoring must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Pilot monitoring must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Daily monitoring must cover login, sync, sales, payments/QRIS, receipt/printer, offline cash, inventory, reports, closing, subscription/device, and admin/onboarding health signals.
8. Hypercare issue triage must classify severity, blocking status, owner, SLA, and status.
9. BLOCKER/CRITICAL open hypercare issues must force NO-GO unless explicitly accepted as outside scope with documented risk.
10. MAJOR open hypercare issues must force WATCH unless explicitly accepted with documented mitigation.
11. Operator feedback must be captured through a safe template without real passwords, secrets, or private customer data.
12. Monitoring artifacts must not contain real payment gateway secrets, server credentials, `.env`, APK/AAB, keystore, or production customer data.
13. Monitoring commands must not print secret values.
14. Monitoring commands must not mutate production data.
15. Sprint 16 must not implement new business features.
16. Sprint 16 must not perform automatic production deployment.
17. Sprint 16 must not send real Slack/WhatsApp/email alerts.
18. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, and field trial behavior from previous sprints must remain intact.
19. Android CI remains the authoritative build gate if local Android build cannot run.
20. Hypercare GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 17 Pilot Stabilization & Defect Burn-down Foundation Runtime Rule

Starting Sprint 17, pilot stabilization must be defect-driven, SLA-aware, audit-safe, tenant-aware, and evidence-backed before a GO tag is created.

Mandatory:

1. Stabilization must not be declared GO without backend tests passing.
2. Stabilization must not be declared GO without Android CI assembleDebug passing.
3. Stabilization must not be declared GO without Android CI testDebugUnitTest passing.
4. Stabilization must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Stabilization must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Stabilization must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Stabilization must not be declared GO without Sprint 16 monitoring/hypercare commands running successfully.
8. Pilot defects must be persisted with severity, status, blocking flag, owner, area, tenant/store context when available, SLA due timestamp, and evidence reference.
9. Defect lifecycle changes must append immutable event records.
10. Defect status changes must not delete history.
11. BLOCKER/CRITICAL open defects must force NO-GO unless explicitly accepted as outside scope with documented risk.
12. MAJOR open defects must force WATCH unless accepted with mitigation.
13. SLA breach detection must flag overdue BLOCKER/CRITICAL/MAJOR defects.
14. Accepted-risk defects must record approver, reason, expiry/review date, and must not hide the original severity.
15. Retest verification must record retest result, verifier, timestamp, and evidence reference.
16. Burn-down summary must count defects by severity, status, area, SLA breach, accepted risk, and fix verification state.
17. Stabilization commands must not print secret values.
18. Stabilization commands must not mutate production data unless explicitly intended as admin defect register mutation and audit/event-tracked.
19. Sprint 17 must not implement new business features.
20. Sprint 17 must not perform automatic production deployment.
21. Sprint 17 must not send real Slack/WhatsApp/email alerts.
22. Sprint 17 must not commit APK/AAB, signing key, keystore, `.env`, DB dump, server credential, or payment gateway secret.
23. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, and hypercare behavior from previous sprints must remain intact.
24. Android CI remains the authoritative build gate if local Android build cannot run.
25. Stabilization GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 18 Pilot Closure & Production Handover Foundation Runtime Rule

Starting Sprint 18, pilot closure and production handover must be sign-off-driven, evidence-backed, defect-aware, risk-reviewed, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Production handover must not be declared GO without backend tests passing.
2. Production handover must not be declared GO without Android CI assembleDebug passing.
3. Production handover must not be declared GO without Android CI testDebugUnitTest passing.
4. Production handover must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Production handover must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Production handover must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Production handover must not be declared GO without Sprint 16 monitoring/hypercare commands running successfully.
8. Production handover must not be declared GO without Sprint 17 stabilization/defect commands running successfully.
9. Pilot closure must include final defect review.
10. Pilot closure must include final accepted-risk review.
11. Pilot closure must include operator/admin handover checklist.
12. Pilot closure must include support/SLA handover.
13. Pilot closure must include backup/restore handover.
14. Pilot closure must include release ownership matrix.
15. Pilot closure must include production readiness sign-off.
16. Production handover packages must be persisted with status, decision, checklist, evidence references, and created/approved actors.
17. Sign-off records must preserve signer role, decision, timestamp, notes, and evidence reference.
18. Sign-off changes must not delete previous package evidence.
19. Open BLOCKER/CRITICAL defects must force NO-GO unless valid accepted risk is documented and explicitly reviewed.
20. Open MAJOR defects must force WATCH unless accepted with mitigation.
21. Expired accepted risk must force NO-GO or WATCH according to severity and config.
22. Production handover commands must not print secret values.
23. Production handover artifacts must not contain real passwords, real payment gateway secrets, server credentials, `.env`, APK/AAB, keystore, or production customer data.
24. Sprint 18 must not implement new business features.
25. Sprint 18 must not perform automatic production deployment.
26. Sprint 18 must not send real Slack/WhatsApp/email alerts.
27. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, and stabilization behavior from previous sprints must remain intact.
28. Android CI remains the authoritative build gate if local Android build cannot run.
29. Production handover GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 19 Production Operations Baseline & Post-Handover Governance Foundation Runtime Rule

Starting Sprint 19, production operations must be evidence-backed, incident-aware, SLA-aware, backup/restore-aware, release/rollback-aware, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Production operations must not be declared GO without backend tests passing.
2. Production operations must not be declared GO without Android CI assembleDebug passing.
3. Production operations must not be declared GO without Android CI testDebugUnitTest passing.
4. Production operations must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Production operations must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Production operations must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Production operations must not be declared GO without Sprint 16 monitoring/hypercare commands running successfully.
8. Production operations must not be declared GO without Sprint 17 stabilization/defect commands running successfully.
9. Production operations must not be declared GO without Sprint 18 closure/handover commands running successfully.
10. Production operation runs must persist decision, status, health signals, evidence references, and actor metadata.
11. Production incidents must persist severity, status, SLA due timestamp, breach timestamp, owner, area, impact, and evidence reference.
12. Incident lifecycle changes must preserve history through metadata or event-style append records when applicable.
13. Open P0/P1 production incidents must force NO-GO unless accepted risk is explicitly documented and reviewed.
14. Open P2 incidents must force WATCH unless mitigation is documented.
15. Backup/restore governance must be checked before GO.
16. Support/SLA governance must be checked before GO.
17. Maintenance windows must be tracked and must not silently hide production risk.
18. Release/rollback governance must preserve candidate commit/tag, rollback reference, owner, and evidence.
19. Operations commands must not print secret values.
20. Operations commands must not execute real production deployment.
21. Operations commands must not send real Slack/WhatsApp/email alerts.
22. Operations artifacts must not contain real passwords, real payment gateway secrets, server credentials, `.env`, APK/AAB, keystore, or production customer data.
23. Sprint 19 must not implement new business features.
24. Sprint 19 must not perform automatic production deployment.
25. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, and handover behavior from previous sprints must remain intact.
26. Android CI remains the authoritative build gate if local Android build cannot run.
27. Post-handover production operations GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 20 Commercial Launch Readiness & SaaS Packaging Foundation Runtime Rule

Starting Sprint 20, commercial launch must be package-governed, pricing-aware, onboarding-capacity-aware, risk-reviewed, sign-off-driven, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Commercial launch must not be declared GO without backend tests passing.
2. Commercial launch must not be declared GO without Android CI assembleDebug passing.
3. Commercial launch must not be declared GO without Android CI testDebugUnitTest passing.
4. Commercial launch must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Commercial launch must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Commercial launch must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Commercial launch must not be declared GO without Sprint 16 monitoring/hypercare commands running successfully.
8. Commercial launch must not be declared GO without Sprint 17 stabilization/defect commands running successfully.
9. Commercial launch must not be declared GO without Sprint 18 closure/handover commands running successfully.
10. Commercial launch must not be declared GO without Sprint 19 production operations commands running successfully.
11. SaaS package catalog must preserve package code, target segment, feature boundaries, device limit, onboarding level, support level, status, and evidence reference.
12. Commercial pricing governance must be consistent with existing `subscription_plans` foundation and must not bypass SubscriptionPlan/TenantSubscription rules.
13. Launch readiness must include package catalog readiness.
14. Launch readiness must include pricing/plan governance.
15. Launch readiness must include sales enablement readiness.
16. Launch readiness must include onboarding capacity readiness.
17. Launch readiness must include commercial risk review.
18. Launch readiness must include launch sign-off.
19. Open CRITICAL/HIGH commercial risks must force NO-GO unless valid accepted risk is documented and reviewed.
20. Open MEDIUM commercial risks must force WATCH unless mitigation is documented.
21. Commercial launch commands must not print secret values.
22. Commercial launch artifacts must not contain real passwords, real payment gateway secrets, server credentials, `.env`, APK/AAB, keystore, or production customer data.
23. Sprint 20 must not implement public signup.
24. Sprint 20 must not implement real billing collection or subscription payment automation.
25. Sprint 20 must not implement public marketing website/pricing page.
26. Sprint 20 must not implement new business features.
27. Sprint 20 must not perform automatic production deployment.
28. Sprint 20 must not send real Slack/WhatsApp/email alerts.
29. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, and operations behavior from previous sprints must remain intact.
30. Android CI remains the authoritative build gate if local Android build cannot run.
31. Commercial launch GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 21 Public Website / Landing Page Readiness Foundation Runtime Rule

Starting Sprint 21, public website and landing page readiness must be content-governed, package-aligned, privacy-aware, lead-safe, SEO-aware, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Public website readiness must not be declared GO without backend tests passing.
2. Public website readiness must not be declared GO without Android CI assembleDebug passing.
3. Public website readiness must not be declared GO without Android CI testDebugUnitTest passing.
4. Public website readiness must not be declared GO without Sprint 13 release readiness commands running successfully.
5. Public website readiness must not be declared GO without Sprint 14 RC/UAT commands running successfully.
6. Public website readiness must not be declared GO without Sprint 15 deployment/field-trial commands running successfully.
7. Public website readiness must not be declared GO without Sprint 16 monitoring/hypercare commands running successfully.
8. Public website readiness must not be declared GO without Sprint 17 stabilization/defect commands running successfully.
9. Public website readiness must not be declared GO without Sprint 18 closure/handover commands running successfully.
10. Public website readiness must not be declared GO without Sprint 19 production operations commands running successfully.
11. Public website readiness must not be declared GO without Sprint 20 commercial launch commands running successfully.
12. Public landing pages must not create tenant/user/subscription/device records.
13. Lead interest submissions must be interest-only and must not perform account creation.
14. Public website package/pricing content must align with commercial package catalog and pricing governance.
15. Public website commands must not print secret values.
16. Public website artifacts must not contain real passwords, real payment gateway secrets, server credentials, `.env`, APK/AAB, keystore, or production customer data.
17. Public website must not include real external analytics/tracking token in Sprint 21.
18. Public website must not include live ad pixel in Sprint 21.
19. Public website must not implement real billing collection.
20. Public website must not implement subscription payment automation.
21. Public website must not perform automatic production deployment.
22. Public website must not send real Slack/WhatsApp/email alerts.
23. Public website must not change Android POS business flow.
24. Public website must include privacy/cookie/terms readiness placeholders before GO.
25. Public website must include public route security tests.
26. Public website must include SEO metadata readiness checks.
27. Public website must include content approval/signoff readiness.
28. Open CRITICAL/HIGH public website risks must force NO-GO unless valid accepted risk is documented and reviewed.
29. Open MEDIUM public website risks must force WATCH unless mitigation is documented.
30. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, operations, and commercial launch behavior from previous sprints must remain intact.
31. Android CI remains the authoritative build gate if local Android build cannot run.
32. Public website GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 22 Lead Management / Sales Pipeline Readiness Foundation Runtime Rule

Starting Sprint 22, lead management and sales pipeline readiness must be admin-governed, audit-logged, secret-safe, manual-review-first, and gated before a GO tag is created.

Mandatory:

1. Sales pipeline readiness must not be declared GO without backend tests passing.
2. Sales pipeline readiness must not be declared GO without Android CI assembleDebug passing.
3. Sales pipeline readiness must not be declared GO without Android CI testDebugUnitTest passing.
4. Sales pipeline readiness must not be declared GO without Sprint 13 release gates passing.
5. Sales pipeline readiness must not be declared GO without Sprint 14 RC/UAT gates passing.
6. Sales pipeline readiness must not be declared GO without Sprint 15 deployment/field gates passing.
7. Sales pipeline readiness must not be declared GO without Sprint 16 monitoring/hypercare gates passing.
8. Sales pipeline readiness must not be declared GO without Sprint 17 stabilization/defect gates passing.
9. Sales pipeline readiness must not be declared GO without Sprint 18 closure/handover gates passing.
10. Sales pipeline readiness must not be declared GO without Sprint 19 production operations gates passing.
11. Sales pipeline readiness must not be declared GO without Sprint 20 commercial launch gates passing.
12. Sales pipeline readiness must not be declared GO without Sprint 21 public website gates passing.
13. Sales leads may be imported from public website lead interest submissions, but must not automatically create tenant records.
14. Sales leads must not automatically create users.
15. Sales leads must not automatically create subscriptions.
16. Sales leads must not automatically register devices.
17. Sales pipeline must not perform real billing collection.
18. Sales pipeline must not perform subscription payment automation.
19. Sales pipeline must not send real WhatsApp/email/Slack alerts.
20. Sales pipeline must not call external CRM APIs in Sprint 22.
21. Sales pipeline activities marked WhatsApp/email are manual notes only.
22. Ready-for-onboarding means manual onboarding review, not automatic provisioning.
23. Admin sales pipeline APIs must be protected by auth:sanctum and platform.admin.
24. Tenant users must not access sales pipeline admin APIs.
25. Sales pipeline mutations must be audit-logged when audit logging exists.
26. Sales pipeline resources and commands must not expose secrets.
27. Sales pipeline risks with open CRITICAL/HIGH severity must force NO-GO unless a valid accepted risk is documented.
28. Sales pipeline risks with open MEDIUM severity must force WATCH unless mitigated.
29. Rejected sales pipeline signoff must force NO-GO.
30. Approved-with-risk sales pipeline signoff must force WATCH.
31. No Android POS business flow may be changed by Sprint 22.
32. No Android sales/admin pipeline UI may be introduced in Sprint 22.
33. No APK/AAB/keystore/signing key/secret may be committed.
34. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, operations, commercial launch, and public website behavior from previous sprints must remain intact.
35. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 23 Billing Collection Governance Foundation Runtime Rule

Starting Sprint 23, SaaS billing collection must be admin-governed, audit-logged, evidence-backed, manual-review-first, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Billing collection readiness must not be declared GO without backend tests passing.
2. Billing collection readiness must not be declared GO without Android CI assembleDebug passing.
3. Billing collection readiness must not be declared GO without Android CI testDebugUnitTest passing.
4. Billing collection readiness must not be declared GO without Sprint 13 release gates passing.
5. Billing collection readiness must not be declared GO without Sprint 14 RC/UAT gates passing.
6. Billing collection readiness must not be declared GO without Sprint 15 deployment/field gates passing.
7. Billing collection readiness must not be declared GO without Sprint 16 monitoring/hypercare gates passing.
8. Billing collection readiness must not be declared GO without Sprint 17 stabilization/defect gates passing.
9. Billing collection readiness must not be declared GO without Sprint 18 closure/handover gates passing.
10. Billing collection readiness must not be declared GO without Sprint 19 production operations gates passing.
11. Billing collection readiness must not be declared GO without Sprint 20 commercial launch gates passing.
12. Billing collection readiness must not be declared GO without Sprint 21 public website gates passing.
13. Billing collection readiness must not be declared GO without Sprint 22 sales pipeline gates passing.
14. SaaS billing invoices are platform-to-tenant billing records and must not be mixed with tenant POS cashier/customer payments.
15. Billing collection must not call payment gateway APIs in Sprint 23.
16. Billing collection must not auto-charge tenants.
17. Billing collection must not automate subscription payment collection.
18. Billing collection must not generate public payment links.
19. Billing collection must not auto-suspend tenant access.
20. Billing collection must not auto-renew subscriptions.
21. Billing collection must not change device limits automatically.
22. Billing collection payment evidence is manual evidence only.
23. Manual QRIS reference is a label only and must not call QRIS runtime.
24. Billing collection activities marked WhatsApp/email are manual notes only.
25. Billing collection must not send real WhatsApp/email/Slack/Telegram messages.
26. Billing collection must not integrate with real CRM or accounting APIs in Sprint 23.
27. Admin billing APIs must be protected by auth:sanctum and platform.admin.
28. Tenant users must not access billing collection admin APIs.
29. Billing collection mutations must be audit-logged when audit logging exists.
30. Billing collection resources and commands must not expose secrets.
31. Billing collection risks with open CRITICAL/HIGH severity must force NO-GO unless a valid accepted risk is documented.
32. Billing collection risks with open MEDIUM severity must force WATCH unless mitigated.
33. Rejected billing collection signoff must force NO-GO.
34. Approved-with-risk billing collection signoff must force WATCH.
35. Invoice totals must be server-calculated from invoice lines.
36. Payment evidence acceptance must update invoice paid/remaining status through service governance only.
37. Rejected payment evidence must not update invoice paid amount.
38. Voided invoices must not accept payment evidence.
39. No Android POS business flow may be changed by Sprint 23.
40. No Android billing/admin UI may be introduced in Sprint 23.
41. No APK/AAB/keystore/signing key/secret may be committed.
42. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, operations, commercial launch, public website, and sales pipeline behavior from previous sprints must remain intact.
43. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 24 Subscription Renewal & Dunning Governance Foundation Runtime Rule

Starting Sprint 24, subscription renewal and dunning governance must be admin-governed, audit-logged, evidence-backed, manual-review-first, secret-safe, and gated before a GO tag is created.

Mandatory:

1. Subscription renewal readiness must not be declared GO without backend tests passing.
2. Subscription renewal readiness must not be declared GO without Android CI assembleDebug passing.
3. Subscription renewal readiness must not be declared GO without Android CI testDebugUnitTest passing.
4. Subscription renewal readiness must not be declared GO without Sprint 13 release gates passing.
5. Subscription renewal readiness must not be declared GO without Sprint 14 RC/UAT gates passing.
6. Subscription renewal readiness must not be declared GO without Sprint 15 deployment/field gates passing.
7. Subscription renewal readiness must not be declared GO without Sprint 16 monitoring/hypercare gates passing.
8. Subscription renewal readiness must not be declared GO without Sprint 17 stabilization/defect gates passing.
9. Subscription renewal readiness must not be declared GO without Sprint 18 closure/handover gates passing.
10. Subscription renewal readiness must not be declared GO without Sprint 19 production operations gates passing.
11. Subscription renewal readiness must not be declared GO without Sprint 20 commercial launch gates passing.
12. Subscription renewal readiness must not be declared GO without Sprint 21 public website gates passing.
13. Subscription renewal readiness must not be declared GO without Sprint 22 sales pipeline gates passing.
14. Subscription renewal readiness must not be declared GO without Sprint 23 billing collection gates passing.
15. Subscription renewal and dunning must not be mixed with POS QRIS/cash customer payments.
16. Subscription renewal and dunning must not call payment gateway APIs in Sprint 24.
17. Subscription renewal and dunning must not auto-charge tenants.
18. Subscription renewal and dunning must not automate subscription payment collection.
19. Subscription renewal and dunning must not generate public payment links.
20. Subscription renewal and dunning must not expose a public renewal portal.
21. Subscription renewal and dunning must not auto-suspend tenant access.
22. Subscription renewal and dunning must not auto-reactivate tenant access.
23. Subscription renewal and dunning must not auto-renew subscriptions without explicit manual admin decision.
24. Subscription renewal and dunning must not auto-change subscription plans.
25. Subscription renewal and dunning must not auto-change device limits.
26. Dunning notices are manual queue records only.
27. Dunning notices marked WhatsApp/email/SMS/call are manual action records only.
28. Subscription renewal and dunning must not send real WhatsApp/email/SMS/Slack/Telegram messages.
29. Subscription renewal and dunning must not integrate with real CRM or accounting APIs in Sprint 24.
30. Admin renewal/dunning APIs must be protected by auth:sanctum and platform.admin.
31. Tenant users must not access renewal/dunning admin APIs.
32. Subscription renewal/dunning mutations must be audit-logged when audit logging exists.
33. Subscription renewal/dunning resources and commands must not expose secrets.
34. Subscription renewal risks with open CRITICAL/HIGH severity must force NO-GO unless a valid accepted risk is documented.
35. Subscription renewal risks with open MEDIUM severity must force WATCH unless mitigated.
36. Rejected subscription renewal signoff must force NO-GO.
37. Approved-with-risk subscription renewal signoff must force WATCH.
38. Payment evidence from billing collection must not auto-renew a subscription.
39. Ready-for-manual-renewal means admin review is required, not automatic subscription mutation.
40. Any manual apply renewal endpoint must be explicit, platform.admin-only, audit-logged, tested, and never triggered automatically.
41. No Android POS business flow may be changed by Sprint 24.
42. No Android renewal/dunning/admin UI may be introduced in Sprint 24.
43. No APK/AAB/keystore/signing key/secret may be committed.
44. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, operations, commercial launch, public website, sales pipeline, and billing collection behavior from previous sprints must remain intact.
45. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 25 Tenant Lifecycle Enforcement & Manual Suspension Governance Foundation Runtime Rule

Starting Sprint 25, tenant lifecycle status and manual suspension must be server-side authoritative, admin-governed, audit-logged, precedence-safe over automation, secret-safe, and gated before a GO tag is created.

Canonical tenant lifecycle rules (locked in `config/tenant_lifecycle.php`):

- `TLS-R001` — Tenant lifecycle status must have a single server-side source of truth.
- `TLS-R002` — Manual suspension may only be created/lifted by platform admin authorization.
- `TLS-R003` — Suspended tenants must be blocked by server-side runtime enforcement, not by client UI only.
- `TLS-R004` — Manual suspension has precedence over subscription renewal and dunning automation.
- `TLS-R005` — Suspension mutation must be audit-logged with redacted metadata.
- `TLS-R006` — Suspension reason is mandatory and must not contain secrets or sensitive payment credentials.
- `TLS-R007` — Tenant-scoped routes must use lifecycle guard or an explicitly documented allowlist.
- `TLS-R008` — Platform/admin routes, billing callbacks, and health routes must not be accidentally locked by tenant suspension.
- `TLS-R009` — Android/POS client must handle suspension responses gracefully but must never be the enforcement authority.
- `TLS-R010` — Sprint 25 GO requires `tenant-lifecycle:go-no-go` green.

Mandatory:

1. Tenant lifecycle status must be computed only by `TenantLifecycleService`; controllers must not recompute it ad-hoc.
2. The lifecycle status vocabulary is onboarding, active, grace, past_due, suspended, cancelled, archived.
3. Manual suspension is the only writer of `tenant_manual_suspensions`; an ACTIVE row means the tenant is suspended.
4. Manual suspend/lift must be platform.admin-only and protected by auth:sanctum.
5. Manual suspend/lift must be idempotent — re-suspending or lifting an already-in-state tenant is a safe no-op.
6. Suspension reason is mandatory; the reason is sanitized so no secret/token/payment credential is persisted.
7. Every suspend/lift must append a `tenant_lifecycle_events` record and an `admin_audit_logs` record with redacted metadata.
8. Suspended tenants must be blocked from operational (POS) routes by the `tenant.lifecycle` server-side guard (423 Locked, `TENANT_SUSPENDED`).
9. The enforcement allowlist (health, auth login/logout/me, tenant-context, subscription status, device register/heartbeat/list, billing webhook) must remain reachable while suspended.
10. Platform/admin routes must never be locked by tenant suspension.
11. Subscription renewal and dunning automation must not auto-suspend a tenant.
12. Subscription renewal and dunning automation must not auto-reactivate or lift a manual suspension.
13. Manual suspension has precedence over renewal/dunning automation; only an explicit platform-admin lift clears it.
14. The Android/POS client must handle a `TENANT_SUSPENDED` response gracefully but must never be the enforcement authority.
15. Tenant lifecycle readiness must not be declared GO without Sprint 24 subscription renewal gates passing.
16. Tenant lifecycle readiness must not be declared GO without all cumulative Sprint 13–24 gate commands registered.
17. `tenant-lifecycle:enforcement-audit` must FAIL if any operational tenant route is missing the lifecycle guard.
18. `tenant-lifecycle:readiness` and `tenant-lifecycle:go-no-go` must FAIL if any automation guardrail flag is enabled.
19. Tenant lifecycle resources and commands must not expose secrets.
20. Sprint 25 must not implement automatic tenant suspension or reactivation.
21. Sprint 25 must not implement tenant hard-delete.
22. Sprint 25 must not implement a public tenant suspension API.
23. Sprint 25 must not send real WhatsApp/email/SMS/Slack messages.
24. No Android POS business flow may be changed by Sprint 25.
25. No Android tenant-lifecycle/admin UI may be introduced in Sprint 25.
26. No APK/AAB/keystore/signing key/secret may be committed.
27. Existing platform admin, onboarding, subscription/device, cash, QRIS, receipt, printer, offline sync, inventory, reports, closing, release hardening, RC/UAT, deployment, field trial, monitoring, hypercare, stabilization, closure, handover, operations, commercial launch, public website, sales pipeline, billing collection, and subscription renewal/dunning behavior from previous sprints must remain intact.
28. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 26 Tenant Plan, Feature Entitlement & Usage Limit Governance Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/tenant_plan.php`, mirrored here, exercised by tests/gates):

- `TPE-R001` — Tenant plan must have a single server-side source of truth.
- `TPE-R002` — Feature entitlement must be enforced server-side and must not rely on Android/UI visibility.
- `TPE-R003` — Usage limits must be evaluated by a central tenant usage limit service before protected mutations.
- `TPE-R004` — Tenant lifecycle enforcement must run before entitlement and usage limit enforcement.
- `TPE-R005` — Suspended, cancelled, or archived tenants must not regain access through plan assignment or entitlement override.
- `TPE-R006` — Platform admin authorization is required for plan assignment and entitlement override mutations.
- `TPE-R007` — Plan assignment and entitlement override mutations must be audit-logged with redacted metadata.
- `TPE-R008` — Entitlement denied responses must use a stable machine-readable code such as `FEATURE_NOT_ENTITLED`.
- `TPE-R009` — Usage limit exceeded responses must use a stable machine-readable code such as `USAGE_LIMIT_EXCEEDED`.
- `TPE-R010` — Android may present entitlement/limit UX, but server-side enforcement remains authoritative.
- `TPE-R011` — Sprint 26 GO requires `tenant-plan:go-no-go` green.
- `TPE-R012` — Sprint 26 rules must coexist with Sprint 25 TLS-R001..R010 and must not weaken lifecycle suspension governance.

Mandatory:

1. A tenant's plan must be resolved only by `TenantPlanResolver` from the persisted catalogue (`tenant_plans`/`plan_entitlements`/`plan_usage_limits`), synced from `config/tenant_plan.php`; controllers must not recompute it ad-hoc.
2. A tenant with no active assignment resolves to the safe default (restricted) plan — never an unlimited/bypass plan.
3. Feature entitlement must be decided only by `FeatureEntitlementService`/`TenantEntitlementGuard`; the `tenant.entitled:<feature>` middleware enforces it server-side and denies with 403 `FEATURE_NOT_ENTITLED`.
4. Usage limits must be decided only by `TenantUsageLimitService`/`TenantUsageMeter`; the `tenant.usage.limit:<key>` middleware enforces it server-side and denies with 429 `USAGE_LIMIT_EXCEEDED`.
5. Current usage must be derived from real DB counts, not fragile stored counters; a declared-but-not-yet-meterable limit must report explicitly, never a silent zero.
6. The `tenant.lifecycle` guard must run before `tenant.entitled`/`tenant.usage.limit` on every guarded route; a suspended tenant must still return `TENANT_SUSPENDED`.
7. Plan assignment (`tenant_plan_assignments`) and entitlement override (`tenant_entitlement_overrides`) are platform.admin-only, protected by auth:sanctum, and audit-logged with redacted metadata.
8. An entitlement override reason is mandatory and sanitized; no secret/token/payment credential is persisted.
9. A plan assignment or entitlement override must never re-enable a suspended/cancelled/archived tenant.
10. Plan assignment must never charge, never call a payment gateway, and never mutate subscription renewal/dunning automation (Sprint 24) or manual suspension (Sprint 25).
11. The Android/POS client must map `FEATURE_NOT_ENTITLED` / `USAGE_LIMIT_EXCEEDED` to friendly UX but must never be the enforcement authority.
12. Sprint 26 rules (`TPE-R001..R012`) must coexist with Sprint 25 `TLS-R001..R010`; suspended must remain a blocked lifecycle status.
13. `tenant-plan:enforcement-audit` must FAIL if a guarded route is missing its entitlement/usage guard or the lifecycle guard.
14. `tenant-plan:readiness` and `tenant-plan:go-no-go` must FAIL if any automation guardrail flag is enabled or a required doc is missing.
15. Tenant plan resources and commands must not expose secrets.
16. Existing subscription/device, tenant lifecycle/suspension, subscription renewal/dunning, and all prior-sprint behavior must remain intact. See `docs/sprints/sprint-26-tenant-plan-feature-entitlement-usage-limit-governance-foundation.md`.
17. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 27 Report Export Metering & Usage Event Ledger Governance Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/usage_event_ledger.php`, mirrored here, exercised by tests/gates):

- `UEL-R001` — Tenant usage events must be recorded in a server-side usage event ledger.
- `UEL-R002` — Usage event ledger entries must be append-only by default and must not be mutated by normal runtime flows.
- `UEL-R003` — Usage event metadata must be sanitized and must not store secrets, credentials, tokens, or excessive PII.
- `UEL-R004` — Usage event recording must be idempotent to prevent double counting during retries.
- `UEL-R005` — Monthly usage meters must use a stable server-side period key.
- `UEL-R006` — Report export metering must use `reports.exports.monthly` as the canonical meter key.
- `UEL-R007` — Successful report exports must record exactly one usage event unless an idempotent duplicate is detected.
- `UEL-R008` — Blocked or failed report exports must not increment usage.
- `UEL-R009` — Report export routes must run tenant lifecycle enforcement before entitlement and usage limit enforcement.
- `UEL-R010` — Report export routes must require report entitlement before usage limit consumption.
- `UEL-R011` — Report export usage limit exceeded responses must use stable code `USAGE_LIMIT_EXCEEDED`.
- `UEL-R012` — Android may present report export limit UX, but server-side enforcement remains authoritative.
- `UEL-R013` — Platform admin may inspect usage event summaries, but normal runtime must not expose cross-tenant usage events.
- `UEL-R014` — Sprint 27 GO requires `report-export-metering:go-no-go` green.
- `UEL-R015` — Sprint 27 rules must coexist with Sprint 25 `TLS-R001..R010` and Sprint 26 `TPE-R001..R012`.

Mandatory:

1. Every tenant usage event must be appended to `tenant_usage_events` only through `UsageEventRecorder`/`UsageEventLedgerService`; the ledger is append-only and no normal runtime route updates or deletes an event (`UEL-R001`, `UEL-R002`).
2. Usage event metadata must pass `SanitizesUsageEventMetadata` before persistence; no secret, token, payment credential, or raw PII is stored (`UEL-R003`).
3. Report export metering must record exactly one `report.exported` event per successful export, deduplicated by a per-tenant unique idempotency key (explicit `Idempotency-Key` header or a deterministic tenant+route+user+report+filter+minute fingerprint), so a retry never double counts (`UEL-R004`, `UEL-R007`).
4. A blocked (suspended/unentitled/over-quota) or failed export must never reach the recorder and must never increment usage (`UEL-R008`).
5. Monthly meters (`reports.exports.monthly`) must be derived by counting the ledger for a stable server-side period key (`Y-m`), never a fragile stored counter (`UEL-R005`, `UEL-R006`); the meter is now `meterable: true`.
6. The report export route must carry `tenant.lifecycle` before `tenant.entitled:reports.basic` before `tenant.usage.limit:reports.exports.monthly`; a suspended tenant returns `TENANT_SUSPENDED`, an unentitled tenant `FEATURE_NOT_ENTITLED`, an over-quota tenant `429 USAGE_LIMIT_EXCEEDED` (`UEL-R009`, `UEL-R010`, `UEL-R011`).
7. Platform admin may inspect tenant/global usage event summaries (`platform.admin` only, read-only, redacted); normal runtime never exposes cross-tenant usage events and there is no runtime update/delete ledger route (`UEL-R013`).
8. The Android/POS client may present report export limit UX but is never the enforcement authority (`UEL-R012`).
9. `report-export-metering:enforcement-audit` must FAIL if the export route is missing the lifecycle, entitlement, or usage guard, or if the meter is not meterable.
10. `usage-event-ledger:readiness` and `report-export-metering:go-no-go` must FAIL if any Sprint 27 guardrail flag is enabled, a required doc is missing, or a prior-sprint gate (Sprint 24/25/26) is not green.
11. Sprint 27 rules (`UEL-R001..R015`) must coexist with Sprint 25 `TLS-R001..R010` and Sprint 26 `TPE-R001..R012`; suspended must remain a blocked lifecycle status (`UEL-R015`).
12. Existing subscription/device, tenant lifecycle/suspension, subscription renewal/dunning, tenant plan/entitlement/usage, and all prior-sprint behavior must remain intact. See `docs/sprints/sprint-27-report-export-metering-usage-event-ledger-governance-foundation.md`.
13. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 28 Usage Ledger Anomaly Detection & Governed Repair Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/usage_ledger_anomaly.php`, mirrored here, exercised by tests/gates):

- `ULR-R001` — Usage ledger anomaly detection must be server-side and must read from the canonical usage event ledger.
- `ULR-R002` — Anomaly detection must be read-only and must not mutate ledger data.
- `ULR-R003` — Duplicate idempotency anomalies must be detected to protect usage counts from retry/double-count drift.
- `ULR-R004` — Missing required ledger fields and invalid period keys must be detected by governance checks.
- `ULR-R005` — Unknown meter keys must be detected against the canonical usage limit registry.
- `ULR-R006` — Metadata anomaly checks must be redacted and must never print secret values.
- `ULR-R007` — Governed repair must default to dry-run and require explicit apply intent.
- `ULR-R008` — Governed repair apply requires reason, actor, audit log, and redacted metadata.
- `ULR-R009` — Normal runtime must not expose update/delete routes for usage ledger events.
- `ULR-R010` — Ledger repair must preserve append-only behavior by using correction events or governed repair records instead of mutating original runtime events.
- `ULR-R011` — Repair operations must be idempotent and must not create duplicate correction drift.
- `ULR-R012` — Admin anomaly visibility must be platform-admin only and must not leak cross-tenant usage data to normal tenants.
- `ULR-R013` — Effective usage after repair must not become negative.
- `ULR-R014` — `reports.exports.monthly` must remain meterable from the usage event ledger after Sprint 28.
- `ULR-R015` — Sprint 28 GO requires `usage-ledger:go-no-go` green.
- `ULR-R016` — Sprint 28 rules must coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, and Sprint 27 `UEL-R001..R015`.

Mandatory:

1. Anomaly detection runs only through `UsageLedgerAnomalyDetector`/`UsageLedgerAnomalyRepository`, reading the append-only `tenant_usage_events` ledger; it never updates, deletes, or appends a ledger event (`ULR-R001`, `ULR-R002`).
2. The detector must catch duplicate double-count drift, missing required fields, invalid quantities, invalid monthly period keys, unknown meter keys, and secret-looking metadata keys — reporting the offending key names only, never their values (`ULR-R003`, `ULR-R004`, `ULR-R005`, `ULR-R006`).
3. Governed repair is CLI-only via `usage-ledger:repair-apply`; there is deliberately no runtime API route that creates, updates, or deletes ledger events or repair records (`ULR-R009`).
4. `usage-ledger:repair-apply` refuses to run without an explicit `--apply` or `--dry-run`, always requires `--reason` and `--actor`, and audit-logs every applied repair with redacted metadata (`ULR-R007`, `ULR-R008`).
5. Repair never mutates or deletes an append-only ledger event; it appends a signed governed correction record (`tenant_usage_ledger_repairs`) and effective usage is derived as ledger count + repair deltas (`ULR-R010`).
6. Repair is idempotent via a per-tenant unique deterministic `repair_key`; re-applying the same plan creates no correction drift (`ULR-R011`).
7. Effective usage after repair is clamped so it can never become negative (`ULR-R013`).
8. Only duplicate double-count drift is auto-repairable; missing-field, invalid-quantity/period, unknown-meter, and suspicious-metadata anomalies are reported for manual review and never auto-mutated (`ULR-R010`).
9. Platform admin may inspect anomaly and repair summaries (`platform.admin` only, read-only, redacted); normal runtime never leaks cross-tenant usage/anomaly data (`ULR-R012`).
10. `reports.exports.monthly` remains `meterable: true` and metered from the ledger after Sprint 28 (`ULR-R014`).
11. `usage-ledger:go-no-go` must FAIL if the detector/planner are not wired, repair apply is not governed, a runtime ledger mutation route exists, a guardrail flag is enabled, redaction is off, the report export meter is not meterable, a required doc is missing, or a prior-sprint gate (Sprint 25/26/27) is not green (`ULR-R015`).
12. Sprint 28 rules (`ULR-R001..R016`) must coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, and Sprint 27 `UEL-R001..R015` (`ULR-R016`). See `docs/sprints/sprint-28-usage-ledger-anomaly-detection-governed-repair-foundation.md`.
13. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 29 Multi-Export Route Metering Coverage & Export Governance Expansions Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/export_governance.php`, mirrored here, exercised by tests/gates):

- `EGC-R001` — Every export-like route must be registered in the canonical export governance registry or explicitly exempted with a reason.
- `EGC-R002` — Export route discovery must be server-side and must be checked by governance commands.
- `EGC-R003` — Metered export routes must run tenant lifecycle enforcement before entitlement and usage limit enforcement.
- `EGC-R004` — Metered export routes must require a report/export entitlement before usage limit consumption.
- `EGC-R005` — Metered export routes must use `reports.exports.monthly` as the canonical meter key unless explicitly exempted.
- `EGC-R006` — Metered export routes must record `report.exported` usage events only after successful export.
- `EGC-R007` — Blocked or failed export routes must not increment usage.
- `EGC-R008` — Export metering must be idempotent and must prevent double counting during retries.
- `EGC-R009` — Export metering metadata must be sanitized and must not store secrets, credentials, tokens, or excessive PII.
- `EGC-R010` — Export exemptions must be explicit, documented, and visible in governance summary.
- `EGC-R011` — Platform admin may inspect export governance coverage, but normal tenants must not see cross-tenant governance data.
- `EGC-R012` — Export governance must not expose runtime bypass routes for metering, usage limits, or usage ledger mutation.
- `EGC-R013` — `reports.exports.monthly` must remain meterable from the usage event ledger after Sprint 29.
- `EGC-R014` — Sprint 29 GO requires `export-governance:go-no-go` green.
- `EGC-R015` — Sprint 29 rules must coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, and Sprint 28 `ULR-R001..R016`.

Mandatory:

1. Every export-like route (a route whose URI ends with a `.csv/.xlsx/.xls/.pdf` extension or carries an `export/download` path segment) must appear in `config('export_governance.routes')` as `metered` or `exempt`; `ExportRouteDiscoveryService` scans the live route table server-side and `export-governance:metering-audit` FAILS on any unregistered export-like route (`EGC-R001`, `EGC-R002`).
2. Metered export routes must carry `tenant.lifecycle` before `tenant.entitled:<feature>` before `tenant.usage.limit:reports.exports.monthly`; a suspended tenant returns `TENANT_SUSPENDED`, an unentitled tenant `FEATURE_NOT_ENTITLED`, an over-quota tenant `429 USAGE_LIMIT_EXCEEDED` (`EGC-R003`, `EGC-R004`).
3. Metered export routes use the canonical `reports.exports.monthly` meter and record exactly one `report.exported` / `report_export` usage event only after a successful export, through `ReportExportMeteringService` (`EGC-R005`, `EGC-R006`).
4. A blocked (suspended/unentitled/over-quota) or failed export must never reach the recorder and must never increment usage; metering is idempotent (explicit `Idempotency-Key` header or deterministic tenant+route+user+report+filter+minute fingerprint) so a retry never double counts (`EGC-R007`, `EGC-R008`).
5. Export metering metadata must pass `SanitizesUsageEventMetadata`; no secret, token, payment credential, or raw PII is stored (`EGC-R009`).
6. Export exemptions must carry an explicit `exempt_reason`, must keep `metering_enabled=false`, and are surfaced in the coverage summary; `export-governance:metering-audit` FAILS on an exemption without a reason (`EGC-R010`).
7. Platform admin may inspect export governance coverage (`platform.admin` only, read-only, redacted route governance — not tenant usage); normal tenants cannot access it (`EGC-R011`).
8. There is deliberately no runtime route that bypasses export metering, overrides the usage limit, or mutates the usage ledger; export governance adds only read-only admin visibility (`EGC-R012`).
9. `reports.exports.monthly` remains `meterable: true` and metered from the ledger after Sprint 29 (`EGC-R013`).
10. `export-governance:go-no-go` must FAIL if the enforcement audit is NO_GO, a Sprint 29 command is missing, a prior-sprint gate (Sprint 25/26/27/28) is not green, the meter is not meterable, or a required doc is missing (`EGC-R014`).
11. Sprint 29 rules (`EGC-R001..R015`) must coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, and Sprint 28 `ULR-R001..R016` (`EGC-R015`). See `docs/sprints/sprint-29-multi-export-route-metering-coverage-export-governance-expansions.md`.
12. Existing subscription/device, tenant lifecycle/suspension, subscription renewal/dunning, tenant plan/entitlement/usage, report export metering, usage ledger anomaly/repair, and all prior-sprint behavior must remain intact.
13. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 30 Billing Invoice Generation & Payment Collection Governance Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/billing_governance.php`, mirrored here, exercised by tests/gates):

- `BIL-R001` — Billing periods must be resolved by a canonical server-side billing period service.
- `BIL-R002` — Tenant invoice generation must be idempotent per tenant and billing period.
- `BIL-R003` — Tenant invoices must be generated from the tenant active plan pricing source of truth.
- `BIL-R004` — Invoice status and payment collection state must use controlled lifecycle services, not ad-hoc controller strings.
- `BIL-R005` — Duplicate active invoices for the same tenant and billing period are forbidden.
- `BIL-R006` — Invoice and payment metadata must be redacted and must not store secrets, tokens, credentials, or excessive PII.
- `BIL-R007` — Billing/payment mutations must be platform-admin only unless explicitly governed otherwise.
- `BIL-R008` — Billing/payment mutations must be audit-logged with reason/actor where applicable.
- `BIL-R009` — Payment records must be idempotent and must not overstate collected revenue.
- `BIL-R010` — Failed or cancelled payments must not mark invoices paid.
- `BIL-R011` — Paid invoices must not automatically lift manual tenant suspension.
- `BIL-R012` — Subscription renewal and dunning services may read billing state but must not bypass invoice/payment lifecycle services.
- `BIL-R013` — Plan price changes must not mutate already issued invoices without a governed adjustment flow.
- `BIL-R014` — Billing invoice generation must not weaken tenant lifecycle, entitlement, usage-limit, usage-ledger, repair, or export-governance gates.
- `BIL-R015` — Sprint 30 GO requires `billing:go-no-go` green.
- `BIL-R016` — Sprint 30 rules must coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, and Sprint 29 `EGC-R001..R015`.

Mandatory:

1. Every billing period is resolved by `BillingPeriodService` (`period_key` = `YYYY-MM`, civil-day-aligned `period_start`/`period_end`, `due_at` = `period_start` + configured `due_days`); no controller/command computes periods ad-hoc (`BIL-R001`).
2. `TenantInvoiceService::generate()` is idempotent per tenant + billing period — a live (non-void, non-cancelled) invoice is returned unchanged on retry — and a `UNIQUE(tenant_id, period_key, source)` index plus a unique `idempotency_key` back it in the schema (`BIL-R002`, `BIL-R005`).
3. Invoice amounts come from `config('billing_governance.pricing')` resolved against the tenant's active Sprint 26 plan; a tenant with no configured/active pricing is refused with a governance error (`BILLING_NO_PLAN_PRICING`), never a silent zero; only an explicitly `free` plan yields a zero total (`BIL-R003`).
4. Invoice `status` (draft|issued|void|cancelled) and `collection_state` (not_due|pending|paid|failed|overdue|written_off|cancelled) move only through `TenantInvoiceStatusService`; illegal transitions throw, and a settled invoice cannot be void/cancelled without a governed reversal (`BIL-R004`).
5. Invoice/payment `metadata` passes `BillingMetadataSanitizer` (drops secret/token/credential/card/KTP/NIK-like keys, truncates long strings); no secrets, gateway payloads, or raw PII are persisted (`BIL-R006`).
6. All billing mutations (generate, void, cancel, record payment, mark failed, cancel payment) are `platform.admin` only and audit-logged via `BillingAuditService` → `AdminAuditLogger`, with a mandatory reason on payment mark-failed/cancel (`BIL-R007`, `BIL-R008`).
7. `TenantPaymentCollectionService::record()` is idempotent, rejects non-positive amounts, and (unless explicitly configured) rejects overpayment and partial payment, so collected revenue is never overstated (`BIL-R009`).
8. A failed or cancelled payment never counts toward `collectedAmount()`, so it never marks an invoice paid; the invoice collection state is always recomputed from the payments that still count (`BIL-R010`).
9. Recording a payment / marking an invoice paid never touches tenant lifecycle — a manually suspended tenant stays suspended (`BIL-R011`); subscription renewal/dunning may read invoice/collection summaries but must mutate only through the invoice/payment services (`BIL-R012`).
10. A plan price change does not mutate an already issued invoice; the stored `total_amount` is fixed at generation (`BIL-R013`).
11. There is deliberately no tenant/public route that can create or mutate invoice/payment state; billing is platform-admin only, and no billing change weakens any Sprint 25–29 gate (`BIL-R014`).
12. `billing:go-no-go` must FAIL if the billing governance audit is NO_GO, a Sprint 30 command is missing, a prior-sprint gate (Sprint 24–29) is not green, or a required doc is missing (`BIL-R015`).
13. Sprint 30 rules (`BIL-R001..R016`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, and Sprint 29 `EGC-R001..R015` (`BIL-R016`). See `docs/sprints/sprint-30-billing-invoice-generation-payment-collection-governance-foundation.md`.
14. Android/UI is not a billing authority; server-side generation and collection state are authoritative.
15. GO/WATCH/NO-GO report must be evidence-backed.
