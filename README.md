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

## Status

Fase saat ini: **Sprint 6 — Printer & Receipt Foundation selesai**. Backend kini
memiliki API preview struk yang tenant-isolated dan payment-aware: struk hanya FINAL/
printable saat penjualan PAID (CASH maupun QRIS), sedangkan penjualan pending/unpaid/
cancelled/expired/failed tidak menghasilkan struk final. Data struk selalu memakai snapshot
sale item, tidak pernah membocorkan `raw_response` atau kredensial gateway. Android memperoleh
layar struk, ESC/POS formatter murni Kotlin, fondasi printer Bluetooth native, dan penyimpanan
pengaturan printer lokal (tanpa kredensial pembayaran). Gradle wrapper kini tersedia dan Android
build CI menjalankan assembleDebug + testDebugUnitTest. Cash (Sprint 4) dan QRIS (Sprint 5)
tetap utuh. Fitur payout/refund/offline sync/inventory dibangun bertahap mengikuti Sprint
Roadmap pada dokumen foundation.
