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

## Status

Fase saat ini: **Sprint 2 — Product Foundation selesai**. Backend kini memiliki
fondasi katalog produk (kategori, produk, override harga per toko) yang tenant-isolated,
serta endpoint sync produk/kategori untuk Android. Fitur penjualan/QRIS dibangun bertahap
mengikuti Sprint Roadmap pada dokumen foundation.
