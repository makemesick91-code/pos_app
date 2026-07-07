# Aish POS Lite — Android POS SaaS untuk UMKM

Aish POS Lite adalah aplikasi kasir (Point of Sale) berbasis **Android** yang dijual sebagai
**SaaS multi-tenant berbasis langganan** untuk UMKM Indonesia (toko, warung, kedai, laundry,
retail kecil).

Aplikasi wajib ringan untuk HP lawas, bisa dijual ke lebih dari satu toko tanpa data antar toko
bercampur, mendukung pembayaran **Cash** dan **QRIS dinamis** via payment gateway, bisa cetak
struk Bluetooth ESC/POS, dan tetap bisa melakukan transaksi cash saat offline.

## Ringkasan Teknis

| Area      | Keputusan                                                        |
| --------- | ---------------------------------------------------------------- |
| Android   | Kotlin native, XML/Views, Room, Retrofit + OkHttp, WorkManager   |
| Backend   | Laravel API, PostgreSQL, Sanctum, Redis/DB queue                 |
| Admin Web | Laravel Blade / Filament                                          |
| Payment   | Cash + QRIS dinamis (Midtrans / Xendit / Duitku / Manual)        |
| Model     | Subscription SaaS multi-tenant                                   |
| Min SDK   | Android 8 / API 26                                               |

## Project Foundation

This project is governed by the official foundation document:

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`

All architecture, sprint planning, implementation, database design, Android design, payment gateway work, offline sync work, subscription work, and deployment work must follow this foundation document.

Lihat juga aturan proyek: `docs/PROJECT_RULES.md`.

## Struktur Repository (target)

```text
pos_app/
├── README.md
├── docs/
│   ├── PROJECT_RULES.md
│   └── foundation/POS_ANDROID_SAAS_FOUNDATION.md   <-- dokumen kunci
├── backend/    (Laravel API)         [dibangun sesuai sprint]
└── android/    (Kotlin native)        [dibangun sesuai sprint]
```

## Project Structure

- `backend/` — Laravel API backend for POS SaaS
- `android/` — Native Android Kotlin POS app
- `docs/` — Foundation, sprint evidence, architecture, and project rules
- `scripts/` — Local validation and smoke scripts

## Sprint 0 — Project Setup

Sprint 0 establishes the initial backend, Android, CI, and validation structure.
Details and evidence: `docs/sprints/sprint-0-project-setup.md`.

Validation:

```bash
bash scripts/sprint0_smoke.sh
```

## Sprint 1 — SaaS Tenant Foundation

Sprint 1 establishes the backend SaaS tenant foundation:

- tenants
- stores
- tenant-aware users (tenant/store/role/is_active)
- Sanctum API authentication
- tenant context middleware
- tenant isolation tests

Details and evidence: `docs/sprints/sprint-1-saas-tenant-foundation.md`.

Validation:

```bash
bash scripts/sprint1_smoke.sh
cd backend && php artisan test
```

Sprint 1 changes the backend only; the Android app is untouched in this sprint.

## Sprint 2 — Product Foundation

Sprint 2 establishes the tenant-isolated product catalog foundation:

- product categories
- products
- store-specific product price overrides
- Android product/category sync endpoints
- product tenant-isolation tests
- application rules lock for foundation + Sprint 0 + Sprint 1 + Sprint 2

Details and evidence: `docs/sprints/sprint-2-product-foundation.md`.

Validation:

```bash
bash scripts/sprint2_smoke.sh
cd backend && php artisan test
```

Sprint 2 changes the backend only; the Android app is untouched in this sprint.

## Sprint 3 — Android Cashier Foundation

Sprint 3 establishes the Android cashier foundation:

- native Kotlin Android cashier app structure
- login foundation using backend auth API
- token/session storage
- Retrofit/OkHttp API client
- Room SQLite local product/category catalog
- product/category sync from backend
- local product search
- cash-first local cart foundation
- Android runtime rules lock

Details and evidence: `docs/sprints/sprint-3-android-cashier-foundation.md`.

Validation:

```bash
bash scripts/sprint3_smoke.sh
cd backend && php artisan test
```

Android build/test may require Android SDK and Gradle wrapper availability.

## Sprint 4 — Sales Backend Integration

Sprint 4 establishes the sales backend integration and cash checkout foundation:

- sales
- sale items
- cash payment records
- invoice number generation
- tenant-isolated sales APIs
- backend recalculated totals and product price snapshots
- Android cart submit to backend
- Android cash checkout success/failure handling
- Sprint 4 runtime rules lock

Validation:

```bash
bash scripts/sprint4_smoke.sh
cd backend && php artisan test
```

Android build/test may require Gradle wrapper, Android SDK, and JDK 17–21 depending on AGP compatibility.

## Sprint 5 — QRIS Payment Gateway Foundation

Sprint 5 establishes the backend-driven QRIS payment gateway foundation:

- QRIS provider abstraction
- fake/sandbox QRIS provider for local/testing
- QRIS payment creation API
- payment status API
- payment webhook logging
- webhook signature validation foundation
- webhook idempotency
- payment reconciliation command
- Android QRIS DTO/API/repository/screen foundation
- Sprint 5 runtime rules lock

Validation:

```bash
bash scripts/sprint5_smoke.sh
cd backend && php artisan test
```

Android build/test may require Gradle wrapper, Android SDK, and JDK 17–21/21 depending on AGP compatibility.

## Sprint 6 — Printer & Receipt Foundation

Sprint 6 establishes the printer and receipt foundation:

- backend receipt preview API
- receipt eligibility rules for CASH and QRIS
- tenant-isolated receipt access
- Android receipt screen
- ESC/POS receipt formatter
- Bluetooth printer foundation
- local printer settings
- Android Gradle wrapper
- Android build CI with assembleDebug and testDebugUnitTest
- Sprint 6 runtime rules lock

Validation:

```bash
bash scripts/sprint6_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 7 — Offline Cash & Sync Foundation

Sprint 7 establishes offline cash and sync foundation:

- backend idempotency/client reference for offline sales
- duplicate offline submit protection
- Android local offline sale storage
- Android WorkManager sync
- retry/backoff foundation
- manual sync status/action
- QRIS online-only guard
- offline receipt draft label
- Android CI with assembleDebug and testDebugUnitTest
- Sprint 7 runtime rules lock

Validation:

```bash
bash scripts/sprint7_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 8 — Inventory Simple Foundation

Sprint 8 establishes the simple ledger-based inventory foundation:

- inventory movements
- stock calculation from signed movement ledger
- opening stock and manual adjustment
- SALE_OUT movement from successful sales
- idempotency-safe offline sale replay
- current stock API
- product stock API
- Android lightweight stock visibility
- Sprint 8 runtime rules lock

Validation:

```bash
bash scripts/sprint8_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 9 — Reports & Closing Foundation

Sprint 9 establishes the simple reports and closing foundation:

- daily sales summary
- payment method summary
- inventory movement summary
- daily closing snapshot
- duplicate closing protection
- tenant-isolated CSV export
- Android lightweight reports screen
- Android closing action/status
- Sprint 9 runtime rules lock

Validation:

```bash
bash scripts/sprint9_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 10 — Subscription & Device Limit Foundation

Sprint 10 establishes the SaaS subscription and device limit foundation:

- subscription plans
- tenant subscriptions
- registered devices
- device limit enforcement
- subscription status API
- device registration and heartbeat API
- device list/revoke foundation
- Android device identity
- Android device registration/status UI
- protected business API enforcement
- Sprint 10 runtime rules lock

Validation:

```bash
bash scripts/sprint10_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 11 — Admin SaaS Control Panel Foundation

Sprint 11 establishes the backend foundation for SaaS platform administration:

- platform admin authorization
- admin tenant list/detail APIs
- admin subscription assign/update APIs
- admin device list/revoke APIs
- admin subscription plan management APIs
- admin audit logs
- audit logging for admin mutations
- tenant-user blocking from admin APIs
- no Android admin panel by design
- Sprint 11 runtime rules lock

Validation:

```bash
bash scripts/sprint11_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 12 — Tenant Onboarding & Demo Data Foundation

Sprint 12 establishes the tenant onboarding and demo data foundation:

- platform-admin tenant onboarding
- default store creation
- owner user creation
- starter/trial subscription assignment
- demo product/category/price data
- opening inventory via inventory movements
- onboarding status/checklist
- guarded demo data reset
- onboarding/demo audit logs
- no public signup
- no Android onboarding/admin panel by design
- Sprint 12 runtime rules lock

Validation:

```bash
bash scripts/sprint12_smoke.sh
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Status

Fase saat ini: **Sprint 10 — Subscription & Device Limit Foundation selesai**.
Akses bisnis kini bergantung pada langganan tenant yang aktif dan perangkat
Android yang terdaftar. Backend memiliki `subscription_plans`,
`tenant_subscriptions`, dan `registered_devices`; keputusan allowed/blocked
dihitung backend dari kolom tanggal (TRIAL/ACTIVE/GRACE diizinkan; EXPIRED/
CANCELLED/SUSPENDED/tanpa langganan diblokir) dan tidak pernah dipercaya dari
Android. Middleware `subscription.active` + `device.registered` melindungi seluruh
API bisnis Sprint 2–9 (produk, sync, sales, struk, QRIS, pembayaran, inventory,
laporan, closing); endpoint auth, status langganan, dan manajemen perangkat tetap
terbuka agar tenant yang diblokir bisa membaca status dan mendaftar/mencabut
perangkat. Batas perangkat (`plan.max_devices`) ditegakkan backend — perangkat
REVOKED tidak dihitung dan pendaftaran berlebih mengembalikan
`DEVICE_LIMIT_REACHED`. Tenant A tidak pernah bisa melihat/mendaftar/mencabut atau
memakai slot perangkat tenant B. Android membuat `device_uuid` sekali secara lokal
(tanpa password/secret gateway), mengirimnya via header `X-Device-UUID`,
mendaftarkan perangkat setelah login, dan menampilkan layar status ringan saat
diblokir (tanpa billing/Play Billing). Sprint 10 tidak mengimplementasikan
penagihan langganan nyata. Cash (Sprint 4), QRIS (Sprint 5), struk/printer
(Sprint 6), offline sync (Sprint 7), inventory (Sprint 8), dan laporan/closing
(Sprint 9) tetap utuh. Android build CI menjalankan assembleDebug +
testDebugUnitTest.

## Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation

Sprint 20 establishes the commercial launch readiness and SaaS packaging foundation:

- commercial launch run persistence
- SaaS package catalog persistence (governance metadata only, no real billing)
- commercial launch signoff persistence
- commercial launch risk register (CRITICAL/HIGH → NO-GO, MEDIUM → WATCH)
- CommercialLaunchReadinessService, SaaSPackageCatalogService, PricingPlanGovernanceService
- SalesEnablementReadinessService, OnboardingCapacityService, CommercialRiskGovernanceService
- CommercialLaunchGoNoGoService (prior gates + commercial readiness aggregate)
- admin commercial APIs behind platform.admin
- commercial:launch-readiness command
- commercial:package-summary command
- commercial:onboarding-capacity command
- commercial:launch-go-no-go command
- commercial docs under docs/commercial/
- CI commercial launch gate (sprint20-ci.yml)
- no public signup / no real billing collection / no payment subscription automation
- no public marketing/pricing page, no new business feature, no auto deploy, no real alerts
- Sprint 20 runtime rules lock

`SubscriptionPlan` / `TenantSubscription` / `RegisteredDevice` (Sprint 10) remain
the runtime subscription and device-limit enforcement source; the package catalog
is an admin-only commercial packaging layer that never bypasses them.

Validation:

```bash
bash scripts/sprint20_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan commercial:launch-readiness --json
cd backend && php artisan commercial:package-summary --json
cd backend && php artisan commercial:onboarding-capacity --json
cd backend && php artisan commercial:launch-go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation

Sprint 19 establishes the production operations baseline and post-handover governance foundation:

- production operation run persistence
- production incident register (P0–P4, SLA-aware, accepted-risk-aware)
- production maintenance window register (rollback-plan-aware)
- ProductionOperationsHealthService (15 health signals → GO/WATCH/NO-GO)
- ProductionIncidentService, BackupRestoreGovernanceService, SupportSlaGovernanceService
- MaintenanceWindowService, ReleaseRollbackGovernanceService, PostHandoverGovernanceReportService
- admin operations APIs behind platform.admin
- production:ops-health command
- production:incident-summary command
- production:backup-governance-check command
- production:post-handover-go-no-go command
- operations runbooks/governance docs under docs/operations/
- CI production operations gate
- no new business feature expansion
- no automatic production deploy
- no real alert sending
- no real backup/restore execution
- Sprint 19 runtime rules lock

Validation:

```bash
bash scripts/sprint19_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:ops-health --json
cd backend && php artisan production:incident-summary --json
cd backend && php artisan production:backup-governance-check --json
cd backend && php artisan production:post-handover-go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 18 — Pilot Closure & Production Handover Foundation

Sprint 18 establishes the pilot closure and production handover foundation:

- pilot closure run persistence
- production handover package persistence
- production handover sign-off records
- final defect review
- accepted risk final review
- support/SLA handover
- backup/restore handover
- operator/admin handover
- release ownership matrix
- production GO/WATCH/NO-GO report
- admin closure/handover APIs behind platform.admin
- pilot:closure-check command
- production:handover-summary command
- production:signoff-summary command
- production:handover-go-no-go command
- CI closure/handover gate
- no new business feature expansion
- no automatic production deploy
- no real alert sending
- Sprint 18 runtime rules lock

Validation:

```bash
bash scripts/sprint18_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
cd backend && php artisan pilot:stabilization-go-no-go --json
cd backend && php artisan pilot:closure-check --json
cd backend && php artisan production:handover-summary --json
cd backend && php artisan production:signoff-summary --json
cd backend && php artisan production:handover-go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation

Sprint 17 establishes the pilot stabilization and defect burn-down foundation:

- persistent pilot defect register
- defect lifecycle event trail
- pilot monitoring run snapshots
- hypercare issue snapshots
- SLA breach detection
- accepted-risk governance
- fix verification/retest workflow
- defect burn-down summary
- stabilization GO/WATCH/NO-GO report
- admin defect APIs behind platform.admin
- pilot:defect-summary command
- pilot:burndown-summary command
- pilot:sla-check command
- pilot:stabilization-go-no-go command
- CI stabilization/defect gate
- no new business feature expansion
- no automatic production deploy
- no real alert sending
- Sprint 17 runtime rules lock

Validation:

```bash
bash scripts/sprint17_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
cd backend && php artisan pilot:defect-summary --json
cd backend && php artisan pilot:burndown-summary --json
cd backend && php artisan pilot:sla-check --json
cd backend && php artisan pilot:stabilization-go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 16 — Pilot Monitoring & Hypercare Foundation

Sprint 16 establishes the pilot monitoring and hypercare foundation:

- pilot daily monitoring command
- pilot health summary command
- hypercare issue triage command
- daily monitoring runbook
- hypercare issue triage workflow
- field issue severity/SLA rules
- operator feedback log
- pilot health summary template
- hypercare GO/WATCH/NO-GO report
- failed sync monitoring checklist
- payment/QRIS monitoring checklist
- device/subscription anomaly checklist
- closing/report monitoring checklist
- CI pilot monitoring/hypercare gate
- no new business feature expansion
- no automatic production deploy
- no real alert sending
- Sprint 16 runtime rules lock

Validation:

```bash
bash scripts/sprint16_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation

Sprint 15 establishes the pilot deployment and field trial evidence foundation:

- pilot deployment checklist
- backend deployment dry-run evidence
- Android RC artifact handling checklist
- operator device readiness checklist
- demo tenant pilot setup evidence
- post-deploy smoke checklist
- pilot rollback checklist
- daily pilot monitoring checklist
- field issue register
- field trial GO/WATCH/NO-GO report
- pilot:deployment-check command
- pilot:field-trial-summary command
- CI pilot deployment/field evidence gate
- no new business feature expansion
- no automatic production deploy
- Sprint 15 runtime rules lock

Validation:

```bash
bash scripts/sprint15_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 14 — Pilot Release Candidate & Operator UAT Foundation

Sprint 14 establishes the pilot RC and operator UAT foundation:

- pilot RC checklist
- operator UAT checklist
- smoke scenario pack
- issue register foundation
- UAT result template
- RC GO/WATCH/NO-GO evidence
- pilot:rc-check command
- pilot:uat-summary command
- CI pilot RC/UAT gate
- no new business feature expansion
- no automatic production deploy
- Sprint 14 runtime rules lock

Validation:

```bash
bash scripts/sprint14_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Sprint 13 — Production Readiness & Release Hardening Foundation

Sprint 13 establishes the release hardening foundation:

- production readiness checks
- release GO/NO-GO command
- environment safety validation
- migration/storage/cache/session/queue readiness checks
- backup/restore runbook
- release checklist/runbook
- Android release readiness checks
- version/build governance
- Sprint 13 CI release gate
- Sprint 13 runtime rules lock

Validation:

```bash
bash scripts/sprint13_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

### Riwayat: Sprint 9 — Reports & Closing Foundation

Sprint 9 — **Reports & Closing Foundation selesai**. Laporan kini
dihasilkan backend secara otoritatif: ringkasan penjualan harian (hanya sale
`PAID` dihitung sebagai revenue; QRIS pending/cancelled tidak), ringkasan
pembayaran per metode/status, dan ringkasan gerakan inventory dari
`inventory_movements`. Snapshot closing harian dikunci satu per
tenant/store/business_date — permintaan closing duplikat me-replay baris yang ada
(`meta.duplicate_replay=true`) tanpa membuat baris ganda, dan seluruh total
dihitung backend (total dari client diabaikan). Export CSV ringkas bersifat
tenant-isolated dan tidak pernah membocorkan payload gateway/secret. Android
menampilkan layar "Ringkasan Harian" ringan (tanpa chart/PDF/Excel) yang hanya
menampilkan nilai backend dan tombol "Tutup Hari Ini". Cash (Sprint 4), QRIS
(Sprint 5), struk/printer (Sprint 6), offline sync (Sprint 7), dan inventory
(Sprint 8) tetap utuh. Android build CI menjalankan assembleDebug +
testDebugUnitTest.

### Riwayat: Sprint 8 — Inventory Simple Foundation

Sprint 8 — **Inventory Simple Foundation selesai**. Stok
dihitung dari ledger `inventory_movements` (sum `signed_qty`) — tidak ada kolom
stok mutable sebagai sumber kebenaran. Penjualan CASH yang berhasil (online dan
sync offline) otomatis membuat gerakan `SALE_OUT` per item untuk produk
stock-tracked, mereferensikan `sale_item` sehingga replay offline yang idempotent
tidak pernah menggandakan pengurangan stok. Endpoint adjustment mendukung
`OPENING`/`ADJUSTMENT_IN`/`ADJUSTMENT_OUT` dengan `signed_qty` dihitung backend;
`SALE_OUT` tidak dapat dibuat manual. Semua endpoint inventory tenant/store
isolated. Android menampilkan label stok ringan ("Stok: -" bila belum diketahui,
peringatan bila ≤ 0) tanpa laporan berat, dan backend tetap otoritatif. Cash
(Sprint 4), QRIS (Sprint 5), struk/printer (Sprint 6), dan offline sync (Sprint 7)
tetap utuh. Android build CI menjalankan assembleDebug + testDebugUnitTest.

### Riwayat: Sprint 7 — Offline Cash & Sync Foundation

Sprint 7 — **Offline Cash & Sync Foundation selesai**. Transaksi
CASH kini dapat dibuat offline: Android menyimpan penjualan ke antrean lokal (Room)
dengan `client_reference` UUID, lalu WorkManager (network-aware, exponential backoff)
menyinkronkannya ke backend saat online. Backend bersifat idempotent — submit offline
yang terulang dengan `client_reference` sama tidak pernah membuat penjualan ganda,
mengembalikan `meta.idempotent_replay=true`, tetap menghitung ulang total dan
snapshot nama/harga produk, serta tetap tenant-isolated. QRIS tetap online-only dan
diblokir saat offline. Struk offline diberi label jelas "STRUK OFFLINE / BELUM SYNC".
Keranjang hanya dikosongkan setelah simpan lokal berhasil; sync gagal tetap menyimpan
penjualan sebagai PENDING/FAILED. Cash (Sprint 4), QRIS (Sprint 5), dan struk/printer
(Sprint 6) tetap utuh. Android build CI menjalankan assembleDebug + testDebugUnitTest.
Fitur offline QRIS/inventory/reports/dashboard dibangun bertahap mengikuti Sprint
Roadmap pada dokumen foundation.
