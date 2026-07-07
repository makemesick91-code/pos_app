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
