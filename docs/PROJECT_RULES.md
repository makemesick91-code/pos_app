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

## Sprint 31 Payment Gateway / QRIS Settlement Governance Foundation Runtime Rule

Canonical foundation rules (locked in `backend/config/payment_gateway_governance.php`, mirrored here, exercised by tests/gates):

- `PGW-R001` — A payment gateway provider must be explicitly configured; the default is a deterministic mock.
- `PGW-R002` — No real payment gateway call may be made in CI by default; the mock provider is used.
- `PGW-R003` — A gateway payment intent must be idempotent per invoice, provider, and channel.
- `PGW-R004` — A paid invoice must not be able to create a new payable payment intent.
- `PGW-R005` — A settlement amount must match the invoice outstanding amount unless partial payment is explicitly enabled.
- `PGW-R006` — Overpayment must be rejected unless explicitly enabled.
- `PGW-R007` — A gateway webhook must carry a verified provider signature.
- `PGW-R008` — Webhook replay must be idempotent; a duplicate provider event must never be reprocessed.
- `PGW-R009` — A failed, cancelled, expired, or rejected event must never mark an invoice paid.
- `PGW-R010` — Settlement must use the Sprint 30 payment collection service, never a direct invoice mutation.
- `PGW-R011` — Settlement/intent/event metadata must be redacted and must not store secrets, signatures, or PII.
- `PGW-R012` — Provider reference uniqueness must be enforced so a single provider payment settles once.
- `PGW-R013` — A manual tenant suspension must never be lifted by a payment settlement.
- `PGW-R014` — All admin gateway mutations must require `platform.admin`.
- `PGW-R015` — There must be no tenant/public route that can mutate gateway/intent/settlement state (the verified webhook is not a tenant mutation route).
- `PGW-R016` — Gateway audit/command/API output must not leak secrets or PII.
- `PGW-R017` — Gateway go/no-go must verify Sprint 30 billing-layer compatibility.
- `PGW-R018` — The mock provider must be deterministic for tests and smoke.

Mandatory:

1. Payment intents are created only by `PaymentGatewayIntentService`; the amount always equals the invoice outstanding amount (never client input), a paid/void/cancelled invoice is refused (`GATEWAY_INVOICE_ALREADY_PAID`/`GATEWAY_INVOICE_NOT_PAYABLE`), and creation is idempotent per invoice + provider + channel (`PGW-R003`, `PGW-R004`, `PGW-R005`).
2. Overpayment and partial settlement are rejected unless explicitly enabled in `payment_gateway_governance` (both default false) — enforced both in the settlement service and in the underlying Sprint 30 collection service (`PGW-R005`, `PGW-R006`).
3. Provider webhooks are ingested only by `PaymentGatewayWebhookService`, which verifies the signature (an unsigned/invalid event is stored `rejected` and never processed → HTTP 401), detects replays via `UNIQUE(provider, provider_event_id)` / `UNIQUE(provider, payload_hash)`, and normalizes status before routing (`PGW-R007`, `PGW-R008`).
4. Only a verified `paid` event settles, through `PaymentGatewaySettlementService` → the Sprint 30 `TenantPaymentCollectionService::record()` (idempotency key derived from invoice + `provider_reference`), so a replayed paid event never double-collects; a failed/expired/cancelled event updates intent/event state but never marks the invoice paid (`PGW-R009`, `PGW-R010`, `PGW-R012`).
5. Settlement never touches tenant lifecycle — a manually suspended tenant stays suspended (`PGW-R013`).
6. All intent/event metadata passes `PaymentGatewayRedactor` (reusing the Sprint 30 `BillingMetadataSanitizer`); the raw signature is never stored (only a truncated fingerprint), and no secret/PII appears in config, audit, command, smoke, or API output (`PGW-R011`, `PGW-R016`).
7. All admin gateway mutations are `platform.admin` only and audit-logged; the only unauthenticated write is the signature-verified webhook, which is not a tenant/user mutation route (`PGW-R014`, `PGW-R015`).
8. The Sprint 31 surface is kept SEPARATE from the Sprint 5 POS QRIS surface (`App\Services\Payments`, `/webhooks/payments/{provider}`) and the Sprint 23 `saas_billing_*` / Sprint 30 `tenant_billing_*` invoice surfaces — no route or table collision.
9. `payment-gateway:go-no-go` must FAIL if the gateway governance audit is NO_GO, a Sprint 31 command is missing, a Sprint 24–30 prior gate is not registered, the Sprint 30 billing layer is absent, or a required doc is missing (`PGW-R017`). See `docs/sprints/sprint-31-payment-gateway-qris-settlement-governance-evidence.md`.
10. Sprint 31 rules (`PGW-R001..R018`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, and Sprint 30 `BIL-R001..R016`.
11. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 32 Plan Entitlement Runtime Enforcement & Subscription Access Control Runtime Rule

Canonical foundation rules (locked in `backend/config/entitlement_governance.php`, mirrored in `backend/config/pos_foundation.php` and here, exercised by tests/gates):

- `ENT-R001` — Tenant plan must resolve through the canonical `TenantPlanResolver`; runtime enforcement is enabled by default.
- `ENT-R002` — A missing or unknown plan must fail closed, never fall back to unlimited access.
- `ENT-R003` — Runtime entitlement checks must go through `EntitlementAccessService`, not ad-hoc controller logic.
- `ENT-R004` — Branch creation must enforce the plan branch limit.
- `ENT-R005` — User creation/invitation must enforce the plan user limit.
- `ENT-R006` — Cashier/operator creation or cashier role assignment must enforce the cashier limit.
- `ENT-R007` — Device registration/activation must enforce the plan device limit.
- `ENT-R008` — Outlet/register creation must enforce the outlet/register limit.
- `ENT-R009` — Premium feature routes/actions must enforce feature entitlement.
- `ENT-R010` — Export/report routes/actions must enforce export/report entitlement.
- `ENT-R011` — Unpaid tenants within grace may use allowed degraded access only, never a silent grace extension.
- `ENT-R012` — Unpaid tenants past grace must be blocked or read-only per governance.
- `ENT-R013` — Manual suspension always wins over payment/billing status.
- `ENT-R014` — A paid invoice never automatically lifts a manual tenant suspension.
- `ENT-R015` — Trial tenants follow trial-specific entitlements and expiry rules.
- `ENT-R016` — Expired trials must be blocked or read-only per governance.
- `ENT-R017` — Over-quota tenants must be denied new resource creation but existing data remains readable unless a suspended policy says otherwise.
- `ENT-R018` — Denied entitlement access must be audit-logged with redacted metadata.
- `ENT-R019` — Entitlement decisions must be deterministic and explainable with stable reason codes.
- `ENT-R020` — CLI/API/admin output must not leak secrets or PII.
- `ENT-R021` — The entitlement cache must not create stale privilege escalation.
- `ENT-R022` — Super-admin/platform-admin operations must still be audited when a bypass is explicitly allowed.
- `ENT-R023` — Prior Sprint 24–31 billing/payment/lifecycle semantics must not be bypassed.
- `ENT-R024` — Go/no-go must verify runtime enforcement for all core limits.

Mandatory:

1. The single runtime gate is `App\Services\Entitlements\EntitlementAccessService`, which composes the Sprint 26 `TenantPlanResolver` (fail-closed on unknown plan), the Sprint 32 `EntitlementBillingStateService` (billing/subscription/lifecycle write access), the Sprint 26 `FeatureEntitlementService`, and the Sprint 26 `TenantUsageLimitService` (`ENT-R001`, `ENT-R002`, `ENT-R003`).
2. `EntitlementBillingStateService` resolves a deterministic write-access state in strict precedence: manual suspension (Sprint 25) → subscription status (Sprint 10, trial/active/grace/expired/cancelled) → outstanding billing invoices (Sprint 30, unpaid-within-grace degraded vs unpaid-past-grace read-only). Settlement (Sprint 31) is consulted only through the trusted collection layer that produced the invoice `collection_state`, so a failed/expired/cancelled provider event can never unlock writes (`ENT-R011..R016`, `ENT-R023`).
3. Manual suspension always denies writes and is never lifted by a paid invoice; the resolver checks the suspension source of truth first (`ENT-R013`, `ENT-R014`).
4. Resource creation (branch/outlet/register → `branches.max`, user/cashier → `users.max`, device → `devices.max`) is denied `OVER_QUOTA` at/over the plan cap while reads of existing data still pass (`ENT-R004..R008`, `ENT-R017`). The write gate `entitlement.write` gates only mutating verbs; reads always pass.
5. Premium feature (`entitlement.feature`), export (`entitlement.export`), and report (`entitlement.report`) middleware enforce plan entitlement and billing state and audit denials; export enforcement runs after the Sprint 27 usage meter so the established `USAGE_LIMIT_EXCEEDED` contract is preserved (`ENT-R009`, `ENT-R010`).
6. Every denied/degraded/read_only/bypassed decision is persisted to `tenant_entitlement_decisions` with metadata redacted by `EntitlementRedactor` (drops secret/token/credential/card/KTP/NIK/phone/email/name keys); routine allowed reads are not persisted, and no secret/PII appears in config, audit, command, smoke, docs, or API output (`ENT-R018`, `ENT-R019`, `ENT-R020`).
7. The admin surface (`tenant-billing/entitlements/*`) is `platform.admin` only and READ-ONLY; there is deliberately no admin or tenant route that mutates entitlement state (`tenant_route_can_mutate_entitlement_state_allowed=false`) (`ENT-R022`).
8. `entitlement:go-no-go` must FAIL if the entitlement governance audit is NO_GO, a core limit is unwired, an enforcement middleware is unregistered, a Sprint 32 command is missing, a Sprint 24–31 prior gate is not registered, or a required doc is missing (`ENT-R024`). See `docs/sprints/sprint-32-plan-entitlement-runtime-enforcement-evidence.md`.
9. Sprint 32 rules (`ENT-R001..R024`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, Sprint 30 `BIL-R001..R016`, and Sprint 31 `PGW-R001..R018`; suspended must remain a blocked lifecycle status.
10. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 33 Tenant Onboarding, Trial Activation & First-Branch Provisioning Foundation Runtime Rule

Sprint 33 turns the commercial SaaS foundation into a real, governed onboarding flow for a new UMKM/tenant from zero: create tenant → select/resolve plan → activate trial → provision first branch → owner/admin → first cashier → device/register setup → seed safe defaults → trial-to-paid readiness → deterministic checklist. It is additive and does not change Sprint 23–32 semantics.

Canonical foundation rules (locked in `backend/config/onboarding_governance.php`, mirrored in `backend/config/pos_foundation.php` and here, exercised by tests/gates):

- `ONB-R001` — Tenant onboarding must use the canonical `TenantOnboardingService` orchestrator.
- `ONB-R002` — Tenant plan must resolve through the canonical `TenantPlanResolver`.
- `ONB-R003` — A missing/unknown plan must fail closed, never fall back to unlimited/free.
- `ONB-R004` — Onboarding must be transactional and idempotent.
- `ONB-R005` — An onboarding mutation request must carry a unique idempotency key.
- `ONB-R006` — Tenant creation must be audit-logged with redacted metadata.
- `ONB-R007` — Trial activation must be time-bounded and audit-logged.
- `ONB-R008` — First branch (store) provisioning is required unless disabled by governance.
- `ONB-R009` — Owner/admin user provisioning is required.
- `ONB-R010` — Cashier provisioning must respect the Sprint 32 user/cashier limit.
- `ONB-R011` — Device/register setup must respect the Sprint 32 device/register limit.
- `ONB-R012` — Default seed data must be safe, deterministic, and tenant-isolated.
- `ONB-R013` — No onboarding step may bypass `EntitlementAccessService`.
- `ONB-R014` — Trial-to-paid transition must use the Sprint 30 invoice/collection services.
- `ONB-R015` — QRIS/payment-intent creation must use the Sprint 31 payment-gateway services.
- `ONB-R016` — A failed/cancelled/expired payment event never activates paid entitlement.
- `ONB-R017` — Manual suspension always wins over onboarding/payment state.
- `ONB-R018` — Public self-signup mutation is disabled unless a signed approval/token flow governs it.
- `ONB-R019` — No tenant/public route may mutate onboarding lifecycle after provisioning without a service guard.
- `ONB-R020` — A provisioning failure must leave an auditable failed state.
- `ONB-R021` — A retry must be idempotent and never duplicate tenant/branch/users/register/device.
- `ONB-R022` — The onboarding checklist must be deterministic and explainable.
- `ONB-R023` — A denied/blocked provisioning step must be audit-logged with redacted metadata.
- `ONB-R024` — Command/API/admin output must not leak secrets or PII.
- `ONB-R025` — Platform-admin bypass must be explicit and audited.
- `ONB-R026` — Go/no-go must verify full Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement Runtime Access compatibility.

Mandatory:

1. The single mutation path is `App\Services\TenantOnboarding\TenantOnboardingService`; controllers/commands never re-implement provisioning (`ONB-R001`, `ONB-R013`). Resource mutations run inside one DB transaction that rolls back on any failure (no half-created tenant), while the run row and a single failed-step row are written OUTSIDE the transaction so a failure always leaves an auditable failed state (`ONB-R004`, `ONB-R020`). A run is idempotent by `idempotency_key`: a replayed key resumes/returns the run and never duplicates a tenant/branch/user/register/device (`ONB-R005`, `ONB-R021`).
2. The plan resolves through the Sprint 26 `TenantPlanResolver`; an unknown plan fails CLOSED with reason `UNKNOWN_PLAN` and there is no unlimited/free fallback (`ONB-R002`, `ONB-R003`, `unknown_plan_grants_unlimited_allowed=false`). Trial activation is time-bounded (`default_duration_days` ≤ `max_duration_days`) and only for trial-eligible plans (`ONB-R007`).
3. Each provisioning step enforces the Sprint 32 `EntitlementAccessService` before it creates a resource — `canCreateBranch` (first store), `canCreateUser` (owner/admin), `canCreateCashier` (first cashier), `canRegisterDevice` (device/register setup) — and a denied step is audit-logged to the provisioning trace and the `tenant_entitlement_decisions` trail with redacted metadata (`ONB-R008..R013`, `ONB-R023`).
4. The device/register step mints only a ONE-TIME setup token: it is hashed and never persisted, returned, or logged; only a short non-reversible fingerprint reaches the trace (`ONB-R011`, `ONB-R024`, `device_setup_token_hashed_only=true`). Default seed data is a small deterministic, tenant-isolated set of product categories; no fake production transactions by default (`ONB-R012`).
5. Trial-to-paid readiness generates the first invoice through the Sprint 30 `TenantInvoiceService` and the QRIS/mock payment intent through the Sprint 31 `PaymentGatewayIntentService`; onboarding NEVER marks an invoice paid or unlocks paid entitlement directly. Paid access only ever follows the trusted Sprint 30 collection state consumed by the Sprint 32 `EntitlementBillingStateService`, so a failed/cancelled/expired payment event can never activate paid access (`ONB-R014..R016`, `onboarding_marks_invoice_paid_directly_allowed=false`, `failed_payment_activates_paid_access_allowed=false`).
6. Manual suspension (Sprint 25) always wins over onboarding/payment state and a paid invoice never lifts it (`ONB-R017`, `paid_invoice_lifts_manual_suspension_allowed=false`). The admin surface (`tenant-billing/onboarding/*`) is `platform.admin` only; there is deliberately NO public/self-signup mutation route (`public_self_signup_mutation_enabled=false`) and NO tenant/public route that mutates onboarding lifecycle (`ONB-R018`, `ONB-R019`).
7. The checklist is deterministic and explainable — every item is derived from a DB existence query or the run's own columns with a stable reason code, and its output is safe (`ONB-R022`, `ONB-R024`).
8. `onboarding:go-no-go` must FAIL if the onboarding governance audit is NO_GO, a hard guardrail is not locked false, a Sprint 33 command is missing, a Sprint 24–32 prior gate is not registered, the central orchestrator wiring is incomplete, or a commercial-chain service is missing (`ONB-R026`). See `docs/sprints/sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning-evidence.md`.
9. Sprint 33 rules (`ONB-R001..R026`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, Sprint 30 `BIL-R001..R016`, Sprint 31 `PGW-R001..R018`, and Sprint 32 `ENT-R001..R024`; the Sprint 33 `tenant_provisioning_runs`/`tenant_provisioning_steps` tables are separate from the Sprint 12 `tenant_onboarding_runs` demo-data table.
10. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 34 Android Offline, Sync, Device Activation & Cashier Runtime Hardening Foundation Runtime Rule

Sprint 34 hardens the Android POS runtime for real UMKM field use: governed, idempotent device/register activation; a stable cashier runtime session; a bounded, deterministic offline queue; and idempotent sync that never double-creates a sale/order. It is additive and does not change Sprint 23–33 semantics. The commercial SaaS chain is now Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement Runtime Access → Tenant Onboarding → **Android Runtime**.

- `ADR-R001` — Android runtime access must resolve tenant/register/device through the canonical backend `AndroidRuntimeAccessService`.
- `ADR-R002` — Device activation must use `DeviceActivationService`.
- `ADR-R003` — The activation token must be hashed/non-reversible and never returned after creation.
- `ADR-R004` — Activation must be idempotent per tenant/register/device fingerprint.
- `ADR-R005` — Activation must respect the Sprint 32 device/register limits.
- `ADR-R006` — Activation must fail closed for an unknown tenant/register/plan.
- `ADR-R007` — Manual suspension blocks Android writes regardless of billing/payment state.
- `ADR-R008` — Unpaid past grace blocks Android writes or forces read-only per governance.
- `ADR-R009` — Trial expired blocks Android writes or forces read-only per governance.
- `ADR-R010` — Cashier login/session must validate tenant, branch, register, device, role, and entitlement.
- `ADR-R011` — Cashier runtime decisions must be audit-logged when denied/degraded.
- `ADR-R012` — Offline sales/orders must carry a client UUID/idempotency key.
- `ADR-R013` — The server must reject a duplicate client UUID without a duplicate mutation.
- `ADR-R014` — A sync batch must be idempotent and retry-safe.
- `ADR-R015` — A failed sync item must be retryable without duplicating a sale/order.
- `ADR-R016` — The conflict policy must be deterministic and explainable.
- `ADR-R017` — Catalog/settings sync must be tenant-isolated.
- `ADR-R018` — Stock/price/customer/payment-method snapshots must not leak other tenants.
- `ADR-R019` — The offline queue must have a bounded size and age.
- `ADR-R020` — Android local storage must avoid raw secrets/PII where possible.
- `ADR-R021` — Android logs must not leak tokens/passwords/PII.
- `ADR-R022` — Sync API output must be redacted and safe.
- `ADR-R023` — Payment settlement state must only come from the Sprint 30/31 trusted services.
- `ADR-R024` — Android must not mark an invoice paid or unlock entitlement locally.
- `ADR-R025` — Entitlement state refresh must fail safe when stale.
- `ADR-R026` — Device revoke/disable must block future sync/write.
- `ADR-R027` — A register/device mismatch must be denied and audited.
- `ADR-R028` — A platform-admin/device-support bypass must be explicit and audited.
- `ADR-R029` — Prior Sprint 24–33 gates must remain green.
- `ADR-R030` — Go/no-go must verify activation, offline queue, sync idempotency, cashier runtime, entitlement, and redaction.

Mandatory:

1. The single Android runtime gate is `App\Services\AndroidRuntime\AndroidRuntimeAccessService`; it delegates the billing/subscription/lifecycle write dimension to the Sprint 32 `EntitlementAccessService` and never re-implements it (`ADR-R001`, `runtime_bypasses_entitlement_service_allowed=false`). Manual suspension always wins (423), unpaid-past-grace fails closed (402/blocked), trial expired fails closed to read-only, unknown plan fails closed — for activation, cashier session and sync alike (`ADR-R006..R009`).
2. Device activation flows only through `DeviceActivationService`. The activation token is stored as a sha256 hash, never persisted raw, never logged, never returned after `prepare()` (`ADR-R002`, `ADR-R003`, `raw_activation_token_stored_allowed=false`, `raw_activation_token_returned_after_creation_allowed=false`). Activation is idempotent per tenant+fingerprint (one `RegisteredDevice`) and runs the Sprint 32 `canRegisterDevice` limit gate (`ADR-R004`, `ADR-R005`).
3. Offline sales/orders carry a client UUID/idempotency key; the server rejects a duplicate client UUID without a second mutation at two levels — a replayed `client_batch_id`/`idempotency_key` resumes the stored batch, and a `client_item_id` already accepted for the tenant is recorded a duplicate. Sale items additionally reuse the Sprint 7 `SaleService` `client_reference` idempotency, so the POS domain service is never bypassed (`ADR-R012..R015`, `sync_bypasses_pos_domain_service_allowed=false`, `duplicate_client_uuid_double_mutation_allowed=false`).
4. A revoked/expired device activation blocks all future sync/write (`AndroidRuntimeAccessService::authorizeSync` denies it and the paired `RegisteredDevice` is moved to `REVOKED`) (`ADR-R026`, `revoked_device_can_sync_allowed=false`). A register/device or tenant mismatch is denied and recorded as a deterministic conflict (`ADR-R027`). Conflicts use stable codes from `config/android_runtime_governance.php` (`ADR-R016`).
5. Android may not invent settlement: a payment sync item is recorded `skipped` (server-only), and no Android route marks an invoice paid or unlocks entitlement locally (`ADR-R023`, `ADR-R024`, `android_marks_invoice_paid_allowed=false`, `android_unlocks_entitlement_locally_allowed=false`). The offline queue is bounded by size and age (`ADR-R019`).
6. Every denied/blocked runtime action is auditable — cashier denials to `admin_audit_logs`, entitlement denials to `tenant_entitlement_decisions` (via the Sprint 32 audit), and sync rejections/conflicts to the `tenant_android_sync_batches`/`tenant_android_sync_items` ledger — all with redacted metadata; no command/API/admin/log output leaks a token hash, fingerprint, raw payload or PII (`ADR-R011`, `ADR-R020..R022`, `raw_credential_in_output_allowed=false`). Platform-admin device revoke/support bypass is explicit and audited (`ADR-R028`).
7. `android-runtime:go-no-go` must FAIL if the governance audit is NO_GO, a hard guardrail is not locked false, a Sprint 34 command is missing, a Sprint 24–33 prior gate is not registered, a runtime service is missing, or a commercial-chain service is missing (`ADR-R030`). See `docs/sprints/sprint-34-android-runtime-hardening-evidence.md`.
8. Sprint 34 rules (`ADR-R001..R030`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, Sprint 30 `BIL-R001..R016`, Sprint 31 `PGW-R001..R018`, Sprint 32 `ENT-R001..R024`, and Sprint 33 `ONB-R001..R026`; the Sprint 34 `tenant_device_activations`/`tenant_android_sync_batches`/`tenant_android_sync_items` tables are additive and separate from the Sprint 10 `registered_devices` table they bridge.
9. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 38 Multi-Tenant Performance Benchmark, Load Gate & Scale Readiness Runtime Rule

Sprint 38 proves the SaaS can handle realistic production-like load across many tenants and operational flows without weakening correctness, tenant isolation, billing, entitlement, import, Android sync, support, or observability. It extends the commercial chain: Plan -> Invoice -> Payment Intent -> Gateway Settlement -> Collection -> Entitlement Runtime Access -> Tenant Onboarding -> Android Runtime -> Support Operations -> Observability -> Data Import/Bootstrap -> Performance/Scale Readiness.

Canonical rules (mirrored in `backend/config/performance_governance.php` and `backend/config/pos_foundation.php`):

- `PERF-R001` — Performance benchmarks must use PerformanceBenchmarkService.
- `PERF-R002` — Benchmark fixtures must be deterministic.
- `PERF-R003` — Benchmark data must be tenant-isolated.
- `PERF-R004` — CI profile must be bounded and non-flaky.
- `PERF-R005` — Heavy profile must not run by default in CI.
- `PERF-R006` — Performance commands must require explicit profile.
- `PERF-R007` — Destructive cleanup must be limited to benchmark-created data.
- `PERF-R008` — Benchmark output must not leak PII/secrets/raw payloads.
- `PERF-R009` — Many-tenant benchmark must verify tenant isolation.
- `PERF-R010` — Product benchmark must verify product/category lookup performance.
- `PERF-R011` — POS transaction benchmark must use existing SaleService/domain service.
- `PERF-R012` — POS benchmark must not double-create transactions.
- `PERF-R013` — Android sync benchmark must use Sprint 34 sync service and idempotency.
- `PERF-R014` — Import benchmark must use Sprint 37 DataImport services.
- `PERF-R015` — Export/report benchmark must preserve Sprint 27-29 metering/governance.
- `PERF-R016` — Billing benchmark must use Sprint 30 invoice/collection services.
- `PERF-R017` — Payment webhook benchmark must use Sprint 31 mock/safe gateway events.
- `PERF-R018` — Entitlement benchmark must use Sprint 32 EntitlementAccessService.
- `PERF-R019` — Onboarding benchmark must preserve Sprint 33 provisioning semantics.
- `PERF-R020` — Support diagnostics benchmark must preserve Sprint 35 read-only support semantics.
- `PERF-R021` — Observability benchmark must integrate Sprint 36 metrics/anomaly services.
- `PERF-R022` — Queue pressure benchmark must not require external queue infra in CI.
- `PERF-R023` — Failed job diagnostics must remain redacted under load.
- `PERF-R024` — Index additions must be justified by query pattern evidence.
- `PERF-R025` — Index review must not remove prior indexes without explicit proof.
- `PERF-R026` — Threshold gate must fail closed on regression.
- `PERF-R027` — Threshold gate must produce explainable reason codes.
- `PERF-R028` — Benchmark snapshots must be auditable and redacted.
- `PERF-R029` — Performance smoke must run in CI.
- `PERF-R030` — Deploy performance smoke must run after pilot/VPS deployment.
- `PERF-R031` — No benchmark may mark invoice paid outside trusted billing/payment services.
- `PERF-R032` — No benchmark may unlock entitlement outside trusted collection state.
- `PERF-R033` — No benchmark may bypass manual suspension.
- `PERF-R034` — No benchmark may reactivate tenant/device.
- `PERF-R035` — Prior Sprint 24-37 gates must remain green.
- `PERF-R036` — Go/no-go must verify multi-tenant, product, POS, Android sync, import, export/report, billing/payment, queue, index/query, observability, smoke-performance, and deploy evidence.

Mandatory:

1. `ci_smoke` must be bounded and CI-safe; `manual_heavy` must never be the default.
2. Benchmark commands must redact PII, secrets, raw sync/import/payment payloads, and raw file contents.
3. The admin performance surface must live under `api/v1/admin/performance/*` behind `platform.admin`; mutation routes require `reason_code`.
4. Query/index review must record evidence before schema changes and must not remove prior indexes without proof.
5. Pilot/VPS deployment and post-deploy smoke/performance evidence are mandatory before Sprint 38 GO. Missing credentials/config means DEPLOY BLOCKED.

## Sprint 37 Data Import, Migration & First Tenant Bootstrap Pack Runtime Rule

Sprint 37 makes new-tenant onboarding operationally practical without direct database work. Platform admins can dry-run and execute CSV imports for categories, products, suppliers, customers, initial stock, prices, payment methods, default settings, and first-tenant bootstrap packs. XLSX is governed/deferred because no safe lightweight parser is installed. The sprint is additive and extends the commercial chain:
Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement Runtime Access → Tenant Onboarding → Android Runtime → Support Operations → Observability → Data Import/Bootstrap.

Canonical rules (mirrored in `backend/config/import_governance.php`, `backend/config/pos_foundation.php`, architecture docs, tests and CI grep):

- `IMP-R001` — Import mutations must use TenantDataImportService.
- `IMP-R002` — Import must be tenant-isolated.
- `IMP-R003` — Import must be dry-run by default.
- `IMP-R004` — Execute import must require explicit execute flag and authorization.
- `IMP-R005` — Import run must have unique idempotency key.
- `IMP-R006` — Row processing must be idempotent through row fingerprint.
- `IMP-R007` — Retry must not duplicate records.
- `IMP-R008` — CSV import must be supported.
- `IMP-R009` — XLSX import must be safe, formula-safe, macro-safe, or explicitly governed/deferred.
- `IMP-R010` — Import validation must happen before mutation.
- `IMP-R011` — Validation errors must be row-addressable and redacted.
- `IMP-R012` — Failed import must leave auditable failed state.
- `IMP-R013` — Import rollback must be safe and limited to records created by the import run.
- `IMP-R014` — Product import must not bypass product/domain service if one exists.
- `IMP-R015` — Category import must be deterministic and tenant-isolated.
- `IMP-R016` — Supplier import must redact PII in output/audit.
- `IMP-R017` — Customer import must redact PII in output/audit.
- `IMP-R018` — Initial stock import must use inventory movement/ledger service where available.
- `IMP-R019` — Initial stock import must never directly corrupt stock balances.
- `IMP-R020` — Price import must use existing pricing/product price service where available.
- `IMP-R021` — Payment method/default settings import must be tenant-scoped.
- `IMP-R022` — Bootstrap pack must integrate with Sprint 33 onboarding state.
- `IMP-R023` — Import denied/blocked actions must be audit-logged.
- `IMP-R024` — Support console must be able to inspect import runs safely.
- `IMP-R025` — Observability must detect failed/stuck imports.
- `IMP-R026` — Import output must not leak secrets/PII/raw file contents.
- `IMP-R027` — Import must not mark invoices paid.
- `IMP-R028` — Import must not unlock entitlement.
- `IMP-R029` — Import must not bypass manual suspension.
- `IMP-R030` — Import must respect Sprint 32 entitlement write state where applicable.
- `IMP-R031` — Import must not reactivate device/tenant.
- `IMP-R032` — Platform-admin bypass must be explicit and audited.
- `IMP-R033` — Prior Sprint 24–36 gates must remain green.
- `IMP-R034` — Go/no-go must verify product/category/supplier/customer/stock/price/payment/default-settings import, dry-run, idempotency, rollback, audit, support, observability, and redaction.

Mandatory:

1. Admin import routes live only under `api/v1/admin/imports/*` behind `platform.admin`; there is no tenant/public import mutation route.
2. Dry-run validation is the default. Execute requires `execute`, `reason_code`, an idempotency key, and the Sprint 32 entitlement write state.
3. CSV is supported. XLSX/XLS/Macro formats are rejected with the governed Sprint 37 deferred reason; formulas are never evaluated.
4. Initial stock writes use the existing inventory movement ledger service. Rollback is limited to import-created records and stock rollback uses a reversal movement.
5. Import run and row state are auditable, tenant-scoped, idempotent, and redacted. Raw file contents and raw paths are never returned.
6. Import cannot mark invoices paid, unlock entitlement, lift manual suspension, reactivate tenants/devices, or mutate gateway settlement state.
7. Support and observability bridges expose safe summaries/signals only.
8. `import:governance-audit`, `import:go-no-go`, and `scripts/sprint37_smoke.sh` must pass before GO.

## Sprint 35 Support Console, Tenant Operations & Incident Diagnostics Foundation Runtime Rule

Sprint 35 makes the SaaS operationally supportable for real tenants WITHOUT opening the database directly: a platform-admin support console for tenant health, a deterministic diagnostic timeline, read-only billing/payment/entitlement/onboarding/device/sync viewers, a blocked/denied-action explorer, sync failure inspection, a governed device revoke/reactivate support flow, tenant-isolated incident notes, a support audit trail, and a time-bound read-only support context. It is additive and does not change Sprint 23–34 semantics.

Canonical rules (mirrored in `backend/config/support_operations_governance.php`, `backend/config/pos_foundation.php` and enforced by tests + `support-ops:governance-audit`/`support-ops:go-no-go`):

- `SUP-R001` — Support console must require platform.admin.
- `SUP-R002` — Tenant health must be computed through `SupportTenantHealthService`.
- `SUP-R003` — Support diagnostics must be tenant-isolated.
- `SUP-R004` — Support console must be read-only by default.
- `SUP-R005` — Support mutations must require an explicit reason code.
- `SUP-R006` — Support actions must be audit-logged with redacted metadata.
- `SUP-R007` — Support output must not leak secrets/PII.
- `SUP-R008` — Support billing viewer must not mutate invoice/payment/collection state.
- `SUP-R009` — Support payment viewer must not bypass Sprint 31 settlement rules.
- `SUP-R010` — Support entitlement viewer must not bypass Sprint 32 enforcement.
- `SUP-R011` — Support onboarding viewer must not mutate Sprint 33 provisioning lifecycle except governed retry/cancel if explicitly supported.
- `SUP-R012` — Support device revoke/reactivate must use Sprint 34 services.
- `SUP-R013` — A revoked device must remain blocked until a governed reactivation.
- `SUP-R014` — Manual suspension always wins over support actions.
- `SUP-R015` — A support action must not mark an invoice paid.
- `SUP-R016` — A support action must not unlock paid entitlement.
- `SUP-R017` — Support read-only context must be tenant-scoped and time-bound.
- `SUP-R018` — Support impersonation is disabled by default unless governed, audited, time-bound, and read-only-safe.
- `SUP-R019` — Support impersonation must never expose raw credentials/tokens.
- `SUP-R020` — The diagnostic timeline must be deterministic and explainable.
- `SUP-R021` — The blocked/denied action explorer must source from audited decisions/logs.
- `SUP-R022` — Sync failure inspection must source from Sprint 34 sync ledgers.
- `SUP-R023` — Support incident notes must be redacted and tenant-isolated.
- `SUP-R024` — Support incident status changes must be audited.
- `SUP-R025` — Support summaries must use safe reason codes.
- `SUP-R026` — A platform-admin bypass must be explicit and audited.
- `SUP-R027` — No tenant/public support mutation route may exist.
- `SUP-R028` — No direct DB-state repair mutation without a governed service.
- `SUP-R029` — Prior Sprint 24–34 gates must remain green.
- `SUP-R030` — Go/no-go must verify tenant health, timeline, incident notes, support audit, device support flow, sync diagnostics, billing/payment/entitlement/onboarding visibility, and redaction.

Mandatory:

1. The whole support console lives under `api/v1/admin/support-ops/*` behind `platform.admin`; there is NO tenant/public support route and no route mutates invoice/payment/collection/settlement/entitlement/onboarding/device state through its own SQL (`SUP-R001`, `SUP-R027`, `SUP-R028`, `support_console_public_or_tenant_mutation_allowed=false`).
2. Every read goes through a governed viewer/health service that only reads the Sprint 30/31/32/33/34 ledgers; the viewers are declared read-only and never mutate the state they read (`SUP-R008..R011`, `viewers_read_only.*=true`). Tenant health is computed only in `SupportTenantHealthService` and manual suspension always resolves to `critical` (`SUP-R002`, `SUP-R014`).
3. Every mutation (device revoke/reactivate, incident create/update, note add, read-only context start/end) requires an enumerable `reason_code` and is written to the `tenant_support_actions` ledger AND mirrored to `admin_audit_logs`, both redacted (`SUP-R005`, `SUP-R006`, `SUP-R024`, `SUP-R026`). No support action can mark an invoice paid, unlock entitlement, bypass settlement or lift/silently reactivate a manual suspension (`SUP-R014..R016`, `support_marks_invoice_paid_allowed=false`, `support_unlocks_entitlement_allowed=false`, `support_lifts_manual_suspension_allowed=false`).
4. Device revoke delegates to the Sprint 34 `DeviceRevocationService` (a revoked device stays blocked); reactivation is disabled by default and returns a governed not-supported response so re-activation must re-run the standard Sprint 34 activation + entitlement/device-limit gate (`SUP-R012`, `SUP-R013`, `support_reactivates_suspended_tenant_allowed=false`).
5. Impersonation is disabled by default (`impersonation.enabled=false`); a start attempt records a governed DENIED action and never returns/persists a raw credential/token (`SUP-R018`, `SUP-R019`, `impersonation_enabled_without_governance_allowed=false`, `impersonation_exposes_raw_credentials_allowed=false`). The read-only context is time-bound (`expires_at`) and expiry is enforced by query/service check (`SUP-R017`).
6. Incidents and notes are tenant-isolated; titles/summaries/note bodies and all metadata are redacted before persistence and no command/API/console output leaks a secret or PII (`SUP-R003`, `SUP-R007`, `SUP-R023`, `support_output_leaks_secret_or_pii_allowed=false`). The diagnostic timeline is deterministic (timestamp desc, then source, then id) and sources only from audited/ledger sources (`SUP-R020`, `SUP-R021`, `SUP-R022`).
7. `support-ops:go-no-go` must FAIL if the governance audit is NO_GO, a hard guardrail is not locked false, a Sprint 35 command is missing, a Sprint 24–34 prior gate is not registered, a support service is missing, or a commercial-chain service is missing (`SUP-R030`). See `docs/sprints/sprint-35-support-console-tenant-operations-incident-diagnostics-evidence.md`.
8. Sprint 35 rules (`SUP-R001..R030`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, Sprint 30 `BIL-R001..R016`, Sprint 31 `PGW-R001..R018`, Sprint 32 `ENT-R001..R024`, Sprint 33 `ONB-R001..R026`, and Sprint 34 `ADR-R001..R030`; the Sprint 35 `tenant_support_incidents`/`tenant_support_incident_notes`/`tenant_support_actions`/`tenant_support_sessions` tables are additive and read every prior ledger without altering it.
9. GO/WATCH/NO-GO report must be evidence-backed.

## Sprint 36 Observability, Health Monitoring, Queue & Production Diagnostics Foundation Runtime Rule

Sprint 36 makes the SaaS production-observable so operational issues are detected before tenants complain: public minimal liveness/readiness endpoints, a platform-admin observability console (application health, database/cache/storage/config diagnostics, queue + failed-job diagnostics, scheduler health, tenant runtime probes), read-only anomaly detection over the Sprint 30–35 ledgers (Android sync, billing/payment webhook, entitlement, onboarding, export/report), safe operational dashboard metrics, a vendor-neutral alert/incident suggestion foundation, and a full go/no-go gate. It is additive and does not change Sprint 23–35 semantics.

Canonical rules (mirrored in `backend/config/observability_governance.php`, `backend/config/pos_foundation.php` and enforced by tests + `observability:governance-audit`/`observability:go-no-go`):

- `OBS-R001` — Public health endpoints must expose minimal non-tenant liveness/readiness only.
- `OBS-R002` — Admin observability routes must require platform.admin.
- `OBS-R003` — Observability diagnostics must be read-only by default.
- `OBS-R004` — Observability output must not leak secrets/PII.
- `OBS-R005` — Database health check must not expose credentials/query payloads.
- `OBS-R006` — Cache health check must not expose keys/values.
- `OBS-R007` — Storage health check must not expose file paths containing tenant/PII unless redacted.
- `OBS-R008` — Queue health must track pending, failed, stale, and long-running risk safely.
- `OBS-R009` — Failed job diagnostics must redact payloads/exceptions.
- `OBS-R010` — Queue retry/replay must be disabled by default or strictly governed, audited, reason-required, and idempotency-aware.
- `OBS-R011` — Scheduler health must detect stale/missed schedules safely.
- `OBS-R012` — Tenant runtime health probes must be tenant-isolated.
- `OBS-R013` — Android sync anomaly detection must source from Sprint 34 ledgers.
- `OBS-R014` — Billing anomaly detection must source from Sprint 30 invoice/collection state.
- `OBS-R015` — Payment webhook anomaly detection must source from Sprint 31 gateway events/intents.
- `OBS-R016` — Entitlement anomaly detection must source from Sprint 32 decision logs/state.
- `OBS-R017` — Onboarding anomaly detection must source from Sprint 33 provisioning runs/steps.
- `OBS-R018` — Support incident suggestion must integrate with Sprint 35 support incidents without auto-mutating tenant state silently.
- `OBS-R019` — Export/report anomaly detection must preserve Sprint 27–29 metering/governance.
- `OBS-R020` — Health summary must be deterministic and explainable with reason codes.
- `OBS-R021` — Alert readiness must be vendor-neutral and CI-safe.
- `OBS-R022` — No external monitoring service required in CI.
- `OBS-R023` — Metrics endpoints must be admin-only unless explicitly safe/minimal.
- `OBS-R024` — Diagnostics must not mark an invoice paid.
- `OBS-R025` — Diagnostics must not unlock entitlement.
- `OBS-R026` — Diagnostics must not reactivate tenant/device.
- `OBS-R027` — Diagnostics must not bypass manual suspension.
- `OBS-R028` — Platform-admin diagnostic actions must be audited.
- `OBS-R029` — Anomaly thresholds must be config-driven.
- `OBS-R030` — Prior Sprint 24–35 gates must remain green.
- `OBS-R031` — No direct DB repair mutation without a governed service.
- `OBS-R032` — Go/no-go must verify health, queue, scheduler, tenant probes, anomaly detection, incident suggestions, audit, and redaction.

Mandatory:

1. Public health lives at `/health/live` and `/health/ready` and returns only `{ status, timestamp }` — no tenant data, no environment secret, no DB credential, no PII (`OBS-R001`, `observability_public_endpoint_exposes_tenant_or_secret_allowed=false`). The whole admin console lives under `api/v1/admin/observability/*` behind `platform.admin` (`OBS-R002`, `OBS-R023`).
2. The console is read-only by default; the only mutations are anomaly acknowledge/resolve, alert-suggestion dismiss/accept and a governed (default-disabled) failed-job retry, each requiring an enumerable `reason_code` and written to `admin_audit_logs` redacted (`OBS-R003`, `OBS-R005`, `OBS-R028`). No route/command marks an invoice paid, unlocks entitlement, reactivates a tenant/device or bypasses a manual suspension (`OBS-R024..R027`, `diagnostics_*_allowed=false`).
3. Infrastructure diagnostics report only driver/store/disk NAMES + booleans — never a DB credential/DSN, cache key/value, or absolute path (`OBS-R005`, `OBS-R006`, `OBS-R007`). Failed-job diagnostics group by a redacted job label and never return the raw payload, exception message or stack trace (`OBS-R009`).
4. Queue retry is disabled by default and returns a governed not-supported (409) response; when ever enabled it is reason-required, audited, and idempotency-safe only (`OBS-R010`, `queue_retry_without_governance_allowed=false`). Scheduler health detects stale/stuck/failed/long-running commands from config thresholds (`OBS-R011`, `OBS-R029`).
5. Anomaly detection is read-only and sources exclusively from the Sprint 34 sync ledgers (`OBS-R013`), Sprint 30 invoice/collection state (`OBS-R014`), Sprint 31 gateway events/intents (`OBS-R015`), Sprint 32 decision ledger (`OBS-R016`), Sprint 33 provisioning runs (`OBS-R017`), and Sprint 27–29 export/report decisions (`OBS-R019`). A scan is dry-run by default; `--execute` persists observability anomaly events ONLY (dedup by tenant + `anomaly_key`, incrementing `occurrence_count`) and never mutates any domain state.
6. Incident suggestion creates SUGGESTIONS only; it never auto-creates a support incident on scan and never mutates tenant state (`OBS-R018`, `incident_suggestion_auto_mutates_tenant_allowed=false`). Accepting a suggestion may create a Sprint 35 support incident ONLY through `SupportIncidentService`, audited. Tenant runtime probes reuse `SupportTenantHealthService` and stay tenant-isolated (`OBS-R012`, `OBS-R020`).
7. Alert readiness is vendor-neutral and CI-safe: no external monitoring vendor, network, or payment credential is required (`OBS-R021`, `OBS-R022`, `external_monitoring_vendor_required_in_ci_allowed=false`). `observability:go-no-go` must FAIL if the governance audit is NO_GO, a hard guardrail is not locked false, a Sprint 36 command is missing, a Sprint 24–35 prior gate is not registered, an observability service is missing, or a commercial-chain service is missing (`OBS-R032`). See `docs/sprints/sprint-36-observability-health-monitoring-queue-production-diagnostics-evidence.md`.
8. Sprint 36 rules (`OBS-R001..R032`) coexist with Sprint 25 `TLS-R001..R010`, Sprint 26 `TPE-R001..R012`, Sprint 27 `UEL-R001..R015`, Sprint 28 `ULR-R001..R016`, Sprint 29 `EGC-R001..R015`, Sprint 30 `BIL-R001..R016`, Sprint 31 `PGW-R001..R018`, Sprint 32 `ENT-R001..R024`, Sprint 33 `ONB-R001..R026`, Sprint 34 `ADR-R001..R030`, and Sprint 35 `SUP-R001..R030`; the Sprint 36 `observability_health_snapshots`/`observability_anomaly_events`/`observability_scheduler_runs`/`observability_alert_suggestions` tables are additive and read every prior ledger without altering it.
9. GO/WATCH/NO-GO report must be evidence-backed.

## Aish POS UIX-1 — Design Foundation & UI Governance (UIX-R001..UIX-R022)

Source of truth once implemented: the app's token/component foundation (`android/app/src/main/res/values/*`,
`backend/resources/css/aish-tokens.css`). The operator UI/UX handoff package is design input only.
Enforced by `scripts/uix1_design_gate.sh` and `.github/workflows/uix1-ci.yml`. Full text:
`docs/foundation/uix-1-design-system.md`.

1. `UIX-R001` — Semantic color tokens only; no hardcoded hex in Android layouts/Kotlin or web components.
2. `UIX-R002` — Typography via defined styles; body ≥ 14 sp; caption reserved for metadata.
3. `UIX-R003` — Spacing uses the 4 dp scale tokens.
4. `UIX-R004` — Touch target ≥ 48 dp; pay/confirm button 52 dp.
5. `UIX-R005` — Financial/numeric text uses tabular figures (`tnum` / `.aish-num`).
6. `UIX-R006` — Every feature screen provides loading, empty, and error states.
7. `UIX-R007` — Offline-aware screens show offline/sync state with the canonical labels.
8. `UIX-R008` — Permission enforced in navigation and actions; backend is the source of truth.
9. `UIX-R009` — Entitlement lock/upgrade states come from the backend decision; never computed client-side.
10. `UIX-R010` — Destructive actions require confirmation; financial actions are server-confirmed only.
11. `UIX-R011` — QRIS/sync never display success before the backend confirms PAID / synced.
12. `UIX-R012` — Offline receipts labelled `*** STRUK OFFLINE / BELUM SYNC ***` until server confirms.
13. `UIX-R013` — Idempotent sync via `client_reference`; failed items not user-deletable; no duplicate UI transactions.
14. `UIX-R014` — Tenant isolation absolute; no cross-tenant data in UI; sensitive admin actions need reason + typed confirmation + audit log.
15. `UIX-R015` — Feature without backend = labelled "SEGERA HADIR" future-state; no active fake button.
16. `UIX-R016` — Reuse foundation components before creating new; no duplicates; explicit variants.
17. `UIX-R017` — Status by icon + label, never color alone; WCAG AA contrast.
18. `UIX-R018` — Motion ≤ 300 ms; respect `prefers-reduced-motion`; no continuous decorative animation.
19. `UIX-R019` — Elevation via border (not heavy shadow/blur); optimized/vector assets; large lists lazy/virtualized.
20. `UIX-R020` — Handoff folder is design input; implemented tokens/components are the app source of truth; deviations documented.
21. `UIX-R021` — New screens registered in the UIX-1 coverage matrix (`docs/uiux/uix-1-screen-coverage.md`).
22. `UIX-R022` — Existing deployment GO tags (`pilot-shared-vps-isolated-deployment-go`, `pilot-shared-vps-post-go-hardening-go`) are immutable; the UIX-1 GO tag is created only on verified evidence.

## Aish POS UIX-2 — Premium Public Experience Rules (UIX2-R001..UIX2-R016)

Enforced by `scripts/uix2_design_gate.sh` and the UIX CI workflow.

1. `UIX2-R001` — Public brand architecture is Aish Tech Solution → Aish POS; “Lite” is not mixed into public product copy.
2. `UIX2-R002` — Homepage composition includes hero product visualization, features, workflow, use cases, offline/QRIS truth, pricing, proof, FAQ, conversion form, and footer.
3. `UIX2-R003` — Every CTA resolves to a real route, anchor, or server-handled form; placeholder links are forbidden.
4. `UIX2-R004` — Product previews represent implemented capabilities and must not imply fictional screens or payment outcomes.
5. `UIX2-R005` — Public pricing comes from approved sources; undecided pricing says “Hubungi Kami” or pilot wording.
6. `UIX2-R006` — Testimonials, customer identities, ratings, logos, and business metrics require approved evidence.
7. `UIX2-R007` — Layouts support 360, 390, 412, 768, 1024, 1280, 1440, and 1920 CSS-pixel viewports without overflow.
8. `UIX2-R008` — Navigation, tabs, disclosure content, forms, focus, landmarks, and touch targets remain keyboard and screen-reader accessible.
9. `UIX2-R009` — Motion is optional, brief, and disabled by reduced-motion preference.
10. `UIX2-R010` — UIX-1 semantic tokens remain the source of truth; new components alias those tokens.
11. `UIX2-R011` — Public pages remain CDN-free and framework-free unless a measured need justifies a locked dependency.
12. `UIX2-R012` — Offline and QRIS labels follow canonical server-confirmed terminology; QRIS is never described as offline.
13. `UIX2-R013` — Pilot wording must not imply self-service signup, public production readiness, or verified HTTPS before those exist.
14. `UIX2-R014` — Public changes require route/content tests, the design gate, and responsive screenshots.
15. `UIX2-R015` — Logo assets require approval; without one, use the documented text lockup and neutral monogram slot.
16. `UIX2-R016` — GO tagging is allowed only after green CI, merge, deployed-commit equality, runtime smoke, backups, and DaengtisiaMS regression evidence.

## Aish POS UIX-3 — Platform Admin Login & SaaS Control Center Foundation (UIX3-R001..UIX3-R016)

Enforced by `backend/tests/Feature/Uix3*`, `scripts/uix3_design_gate.sh`,
`scripts/verify_application_foundation_rules.sh`, and the `uix3-ci.yml` workflow.
Full text: `docs/foundation/uix-3-platform-admin-control-center.md`.

1. `UIX3-R001` — A platform admin is a backend identity (`is_platform_admin` AND `is_active`), never a tenant role; tenant context never grants platform privilege.
2. `UIX3-R002` — The browser console (`/admin/*`) is a distinct surface guarded by `platform.admin.web`, deny-by-default; unauthenticated visitors are redirected, non-admin sessions logged out.
3. `UIX3-R003` — No production default credentials; `platform:admin-provision` takes the password via hidden prompt/STDIN (never a visible CLI arg), validates strength, hashes it, and never logs or stores plaintext.
4. `UIX3-R004` — Login has one generic failure message (no enumeration), per-(email,ip) rate limiting, session regeneration, timing normalization, and no user-supplied redirect (no open redirect).
5. `UIX3-R005` — State-changing admin requests are CSRF-protected; logout is POST-only and invalidates the session.
6. `UIX3-R006` — Authenticated console responses are non-cacheable (`no-store, private`).
7. `UIX3-R007` — Control-center metrics reuse existing governed summary services; no recomputed business status; unavailable groups render truthfully, never a fabricated zero.
8. `UIX3-R008` — Authoritative tenant lifecycle status comes only from `TenantLifecycleService::resolve()`; never recomputed in a controller/view.
9. `UIX3-R009` — Tenant list/detail reuse `AdminTenantService` + `SupportTenantHealthService` (domain-redacted); no password hash, token, secret, or unnecessary PII is rendered.
10. `UIX3-R010` — Read-only foundation: no tenant mutation routes; a mutation requires an existing governed service, policy, idempotency, audit, confirmation UX, and tests — never duplicated logic.
11. `UIX3-R011` — Cross-tenant detail views and console login/logout are audited via `AdminAuditLogger`; audit never stores password/session/token/cookie; failed logins log only a hashed identifier.
12. `UIX3-R012` — Dashboard/list queries are bounded (grouped counts, pagination ≤50, page-scoped resolves); no all-tenant fan-out and no N+1.
13. `UIX3-R013` — The console reuses the UIX-1/UIX-2 tokens (`aish-tokens.css`), build-free Blade; semantic, keyboard-usable, visible focus, `aria-expanded` nav, labels, reduced motion, responsive 360–1920 with no horizontal overflow.
14. `UIX3-R014` — Admin pages are `noindex, nofollow` with a same-origin referrer policy.
15. `UIX3-R015` — Shared-VPS isolation preserved and DaengtisiaMS non-regressed; while HTTPS/domain is absent the portal is reachable only via an encrypted operator channel; public plaintext-HTTP admin usage is NO-GO and stated truthfully in evidence.
16. `UIX3-R016` — Authoritative CI is the `pull_request` workflow set (no fake-green); the UIX-3 GO tag is created only after merge, deploy, runtime verification, and DaengtisiaMS non-regression evidence; existing GO tags are immutable.

## Aish POS UIX-4 — Tenant Owner Web Console (UIX4-R001..UIX4-R022)

Full narrative: `docs/foundation/uix-4-tenant-owner-web-console.md`. Governance:
`docs/governance/tenant-owner-web-console-foundation.md`.

1. `UIX4-R001` — Tenant Owner Web Console (`/owner/*`) is a distinct application surface from the public website, Platform Admin Console, and Android/API.
2. `UIX4-R002` — Tenant Owner access never implies Platform Admin access; the two run on separate session guards.
3. `UIX4-R003` — Platform Admin access never implies Tenant Owner membership; a platform-admin session can never reach `/owner/*`.
4. `UIX4-R004` — Tenant context comes only from authorized server-side membership (the owner's own `tenant_id`), resolved in `OwnerContextResolver`.
5. `UIX4-R005` — Raw tenant IDs from request input (route param, query, header, cookie, hidden field) are never trusted.
6. `UIX4-R006` — Every owner query is tenant-scoped and deny-by-default; there is no automatic global scope, so scoping is explicit.
7. `UIX4-R007` — Outlet/device lookups enforce tenant ownership; a foreign or unknown id resolves to 404, never another tenant's data.
8. `UIX4-R008` — Any multi-tenant owner switching must validate every target membership via POST+CSRF and re-scope; the current domain is single-tenant-per-user so no switcher is exposed.
9. `UIX4-R009` — Existing domain services (`TenantLifecycleService`, `TenantPlanResolver`, entitlement/usage, billing/onboarding/android-runtime viewers, `DailySalesReportService`) remain the source of truth; the console never recomputes business state.
10. `UIX4-R010` — Dashboard values must be truthful; an unavailable read renders as "Tidak tersedia", never a fabricated zero.
11. `UIX4-R011` — UIX-4 is read-only first; no tenant business mutation route exists. A mutation requires an existing governed service, policy, idempotency, audit, confirmation UX, and tests.
12. `UIX4-R012` — Production default owner credentials are forbidden; `tenant:owner-provision` takes the password via hidden prompt/STDIN (never a visible CLI arg), validates strength, hashes it, and never logs or stores plaintext.
13. `UIX4-R013` — Owner web login has one generic failure message (no enumeration), per-(email,ip) rate limiting, timing normalization, session regeneration, POST-only secure logout, and no user-supplied redirect.
14. `UIX4-R014` — Authenticated owner pages are non-cacheable (`no-store, private`).
15. `UIX4-R015` — Any cache keys/data include tenant and identity scope so a cached response cannot cross tenants.
16. `UIX4-R016` — Audit reuses `AdminAuditLogger` (sanitized); no password/session/token/cookie/PII is stored, and device token/fingerprint hashes are never rendered.
17. `UIX4-R017` — Responsive (360–1920, no horizontal overflow) and accessibility (semantic, labelled, keyboard, visible focus, `aria-expanded` nav, reduced motion, `noindex`) gates are mandatory.
18. `UIX4-R018` — Cross-tenant isolation and surface/role-separation tests are release blockers.
19. `UIX4-R019` — Public plaintext-HTTP use with real tenant data is NO-GO; while HTTPS/domain is absent the console is reached only via an encrypted operator/user channel.
20. `UIX4-R020` — Shared-VPS deployment must not change or regress DaengtisiaMS.
21. `UIX4-R021` — GO requires local/origin/VPS exact-match and runtime verification.
22. `UIX4-R022` — Existing GO tags are immutable.

## Aish POS UIX-5 — Subscription, Billing & Invoice Console (UIX5-R001..UIX5-R028)

Full narrative: `docs/foundation/uix-5-subscription-billing-invoice-console.md`.
Governance: `docs/governance/subscription-billing-invoice-foundation.md`. Modular
rule: `.claude/rules/35-subscription-billing-invoice-integrity.md`.

1. `UIX5-R001` — Subscription, entitlement, usage, billing, invoice, renewal, dunning, QRIS, and settlement services remain canonical and are reused, never forked.
2. `UIX5-R002` — Controllers, view models, and Blade templates must not duplicate financial business logic; `BillingConsoleReadService` reads canonical columns/methods only.
3. `UIX5-R003` — Tenant Owner billing access is always tenant-scoped and deny-by-default, from server-resolved `OwnerContext`.
4. `UIX5-R004` — Platform Admin billing access requires the `platform.admin.web` gate and never grants owner membership.
5. `UIX5-R005` — Tenant Owner access never grants platform-global visibility.
6. `UIX5-R006` — Invoice resolution/download enforce the active surface and tenant boundary; foreign/unknown id returns 404; owner invoices never use implicit route-model binding.
7. `UIX5-R007` — Public or unauthenticated invoice URLs are forbidden; no direct-storage invoice URL exists.
8. `UIX5-R008` — Financial values use whole-rupiah integer money types; no unsafe float arithmetic and no `/100` cents conversion.
9. `UIX5-R009` — Currency, rounding, tax, discount, billing-period, and timezone semantics come from the existing domain source of truth.
10. `UIX5-R010` — Invoice totals/balances are displayed from canonical values, never recomputed in views; money is formatted only through `<x-rupiah>`.
11. `UIX5-R011` — Issued, pending, paid, settled, failed, expired, refunded, and void states remain semantically distinct.
12. `UIX5-R012` — QRIS payment state is never presented as settlement unless the canonical settlement source confirms it (only `collection_state = paid` renders "Lunas").
13. `UIX5-R013` — Unknown/unsupported values render as "Tidak tersedia", never a fabricated zero.
14. `UIX5-R014` — Historical invoice/payment evidence is immutable except through governed correction/void in the owning service.
15. `UIX5-R015` — Direct model updates for subscription/invoice/payment/dunning/settlement state are forbidden in UI controllers.
16. `UIX5-R016` — Billing console scope is read-only first unless a governed, idempotent, audited mutation service already exists; UIX-5 ships read-only.
17. `UIX5-R017` — Any financial mutation requires authorization, confirmation, idempotency, audit, tests, and documented compensation behaviour.
18. `UIX5-R018` — Invoice documents use authenticated, authorized, non-path-traversable delivery (filename from the canonical invoice number, never request input).
19. `UIX5-R019` — Invoice documents/logs redact credentials, tokens, webhook secrets, signature/payload hashes, and unnecessary PII.
20. `UIX5-R020` — Billing responses are private/non-cacheable; cache keys include surface, identity, tenant, period, and filter scope.
21. `UIX5-R021` — Subscription and invoice lists are paginated and bounded.
22. `UIX5-R022` — Search and sort fields are explicitly whitelisted.
23. `UIX5-R023` — Cross-tenant invoice list, detail, export, and download tests are mandatory release blockers.
24. `UIX5-R024` — Financial-integrity and status-transition regression tests are mandatory release blockers.
25. `UIX5-R025` — Public plaintext HTTP access involving real billing/invoice data remains NO-GO.
26. `UIX5-R026` — Production Artisan cache operations must preserve PHP-FPM runtime ownership of `storage/framework` and `bootstrap/cache`.
27. `UIX5-R027` — Shared-VPS deployment must not change or regress DaengtisiaMS.
28. `UIX5-R028` — GO requires observed evidence, authoritative CI success, local/origin/VPS exact match, runtime verification, and immutable previous tags.
