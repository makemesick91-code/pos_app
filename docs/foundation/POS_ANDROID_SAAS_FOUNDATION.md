# POS ANDROID SAAS FOUNDATION

> **DOKUMEN KUNCI / SOURCE OF TRUTH**
>
> Dokumen ini adalah **foundation lock** resmi untuk seluruh proyek **Aish POS Lite**.
> Semua sprint, prompt, coding, desain database, desain Android, backend, payment gateway,
> offline sync, subscription, deployment, dan QA **WAJIB** mengikuti dokumen ini.
>
> Tidak ada implementasi yang boleh bertentangan dengan dokumen ini kecuali dokumen ini
> diperbarui terlebih dahulu secara eksplisit.
>
> - **Nama Produk (sementara):** Aish POS Lite
> - **Jenis:** Android POS SaaS Multi-Tenant
> - **Versi Dokumen:** 1.0
> - **Status:** ACTIVE / LOCKED
> - **Tanggal:** 2026-07-06

---

## DAFTAR ISI

1. [Visi Produk](#1-visi-produk)
2. [Tujuan Produk](#2-tujuan-produk)
3. [Prinsip Produk](#3-prinsip-produk)
4. [Target User](#4-target-user)
5. [Model Bisnis](#5-model-bisnis)
6. [Arsitektur Sistem](#6-arsitektur-sistem)
7. [Tech Stack](#7-tech-stack)
8. [Multi-Tenant Foundation](#8-multi-tenant-foundation)
9. [Modul Backend](#9-modul-backend)
10. [Modul Android](#10-modul-android)
11. [Database Foundation](#11-database-foundation)
12. [API Foundation](#12-api-foundation)
13. [QRIS Payment Foundation](#13-qris-payment-foundation)
14. [Offline & Sync Foundation](#14-offline--sync-foundation)
15. [Receipt Printer Foundation](#15-receipt-printer-foundation)
16. [Security Foundation](#16-security-foundation)
17. [Performance Foundation](#17-performance-foundation)
18. [UI/UX Foundation](#18-uiux-foundation)
19. [Admin Web Foundation](#19-admin-web-foundation)
20. [Subscription Guardrail](#20-subscription-guardrail)
21. [MVP Scope](#21-mvp-scope)
22. [Sprint Roadmap](#22-sprint-roadmap)
23. [Testing Foundation](#23-testing-foundation)
24. [Deployment Foundation](#24-deployment-foundation)
25. [No-Go Rules](#25-no-go-rules)
26. [Definition of Done MVP](#26-definition-of-done-mvp)
27. [Struktur Repository](#27-struktur-repository)
28. [Prioritas Eksekusi](#28-prioritas-eksekusi)

---

## 1. VISI PRODUK

Aish POS Lite adalah **aplikasi kasir (Point of Sale) berbasis Android** yang dirancang untuk
UMKM Indonesia dan dijual sebagai **SaaS multi-tenant berbasis langganan**.

Visi:

> Menjadi POS Android paling ringan, paling murah, dan paling mudah dipakai untuk UMKM Indonesia —
> bisa jalan di HP lawas, mendukung pembayaran cash dan QRIS dinamis, tetap bisa transaksi cash
> saat offline, dan bisa dijual ke ribuan toko tanpa data antar toko bercampur.

Aplikasi ini **BUKAN** POS untuk satu toko. Sejak baris kode pertama, aplikasi ini adalah
**platform SaaS multi-tenant** yang melayani banyak toko secara bersamaan dengan isolasi data penuh.

---

## 2. TUJUAN PRODUK

Tujuan konkret yang harus dicapai foundation ini:

```text
1.  Menyediakan POS Android yang ringan dan cepat di HP lawas (API 26+).
2.  Mendukung transaksi CASH dan QRIS dinamis via payment gateway.
3.  Memungkinkan transaksi CASH tetap jalan saat offline.
4.  Menjamin QRIS hanya berjalan saat online dan selalu backend-driven.
5.  Menjamin isolasi data antar tenant/toko secara mutlak.
6.  Menyediakan cetak struk Bluetooth ESC/POS yang stabil.
7.  Mendukung model bisnis subscription dengan paket dan device limit.
8.  Menyediakan admin web untuk manajemen tenant, produk, dan subscription.
9.  Menyediakan laporan harian yang akurat untuk owner toko.
10. Menyediakan mekanisme rekonsiliasi pembayaran QRIS.
11. Menjamin tidak ada credential payment gateway di dalam APK.
12. Menyediakan audit log untuk transaksi dan pembayaran.
```

---

## 3. PRINSIP PRODUK

Prinsip yang tidak boleh dilanggar sepanjang pengembangan:

```text
P1.  RINGAN DULU. Setiap fitur diuji di HP lawas sebelum dianggap selesai.
P2.  MULTI-TENANT SEJAK AWAL. Tidak ada jalur "nanti dibuat multi-tenant".
P3.  ISOLASI DATA MUTLAK. Kebocoran data antar tenant = kegagalan produk.
P4.  BACKEND ADALAH SUMBER KEBENARAN untuk uang (payment, saldo, status).
P5.  OFFLINE-FRIENDLY UNTUK CASH, ONLINE-ONLY UNTUK QRIS.
P6.  KEAMANAN UANG DI ATAS KENYAMANAN. Tidak ada shortcut untuk payment.
P7.  TIDAK ADA SECRET DI ANDROID. API key gateway hanya di backend.
P8.  SIMPLE > CANGGIH. UMKM butuh cepat & jelas, bukan fitur mewah.
P9.  DATA TIDAK BOLEH HILANG. Transaksi offline harus tersimpan aman & sync.
P10. AUDITABLE. Semua transaksi uang harus bisa dilacak dan direkonsiliasi.
```

---

## 4. TARGET USER

```text
Segmen utama:
- UMKM / toko kelontong / warung
- Kedai kopi / cafe kecil
- Laundry
- Retail kecil
- Toko dengan 1–5 kasir

Karakteristik user:
- Awam teknologi.
- Menggunakan HP Android lawas (RAM kecil, storage terbatas).
- Koneksi internet tidak selalu stabil.
- Butuh transaksi cepat saat antri.
- Butuh struk fisik.
- Butuh laporan harian sederhana.

Peran (role) di dalam sistem:
- SUPER ADMIN (SaaS)  : mengelola semua tenant, subscription, device.
- OWNER (tenant)      : pemilik toko, lihat laporan, kelola produk & user.
- CASHIER (tenant)    : melayani transaksi di aplikasi Android.
```

---

## 5. MODEL BISNIS

```text
Model      : Subscription SaaS multi-tenant.
Penjualan  : Per tenant (toko/brand), dengan paket berjenjang.
Penagihan  : Bulanan / tahunan (di luar scope MVP teknis pembayaran langganan).

Paket:
- Starter   : fitur dasar, device limit kecil, 1 store.
- Standard  : device lebih banyak, multi-user, laporan.
- Pro       : multi-store, device lebih banyak, fitur lanjutan.

Sumber pendapatan:
- Biaya langganan bulanan/tahunan per tenant.
- (Opsional masa depan) fee transaksi / add-on.

Catatan penting:
- MVP TIDAK memproses pembayaran langganan otomatis.
- Subscription MVP diatur MANUAL oleh Super Admin (aktif/expired, paket, device limit).
- Yang WAJIB ada di MVP adalah GUARDRAIL subscription (lihat bagian 20).
```

---

## 6. ARSITEKTUR SISTEM

Diagram alur tingkat tinggi:

```text
┌─────────────────────────┐
│   Android POS App        │  (Kotlin, Room, Retrofit, WorkManager)
│   - Kasir / Cashier      │
│   - Offline cash queue   │
└───────────┬─────────────┘
            │ HTTPS (REST JSON, Bearer token)
            ▼
┌─────────────────────────┐
│   Laravel Backend API    │  (Sanctum auth, tenant scoping, queue)
│   - Auth & tenant guard  │
│   - Sales & payment      │
│   - Webhook handler      │
│   - Reconciliation       │
└───────────┬─────────────┘
            │
    ┌───────┴────────┐
    ▼                ▼
┌─────────┐   ┌───────────────────┐
│PostgreSQL│  │ Payment Gateway    │
│(tenant   │  │ QRIS Dinamis       │
│ data)    │  │ (Midtrans/Xendit/  │
└─────────┘   │  Duitku/Manual)    │
              └─────────┬─────────┘
                        │ Webhook (server-to-server)
                        ▼
              ┌───────────────────┐
              │ Laravel Webhook    │
              │ Endpoint (verify)  │
              └───────────────────┘

Admin Web (Blade/Filament) ── HTTPS ──▶ Laravel Backend ──▶ PostgreSQL
```

Prinsip arsitektur:

```text
A1. Android hanya berbicara ke Backend API. Tidak ke gateway langsung.
A2. Backend adalah satu-satunya pihak yang memegang credential gateway.
A3. Webhook gateway hanya diproses & divalidasi di backend.
A4. PostgreSQL adalah single source of truth untuk data server.
A5. Room SQLite adalah cache lokal + antrean offline, BUKAN sumber kebenaran uang.
A6. Semua endpoint operasional wajib ter-scope ke tenant_id dari token.
```

---

## 7. TECH STACK

### 7.1 Android (KEPUTUSAN RESMI)

```text
Language        : Kotlin
UI              : Native Android XML / Android Views (BUKAN WebView untuk POS utama)
Local DB        : Room (SQLite)
Network         : Retrofit + OkHttp
Background Sync  : WorkManager
Printer         : ESC/POS Bluetooth
Minimum SDK     : Android 8 / API 26
Target SDK      : Mengikuti requirement terbaru Google Play Store
Arsitektur app  : MVVM (ViewModel + Repository + Room + Retrofit)
```

Larangan Android:

```text
- DILARANG memakai WebView sebagai POS utama.
- DILARANG UI berat / layout dalam berlebihan.
- DILARANG animasi berlebihan.
- DILARANG gambar produk besar (wajib thumbnail + kompresi).
- DILARANG menyimpan payment gateway key di APK / kode / resource.
```

### 7.2 Backend (KEPUTUSAN RESMI)

```text
Framework   : Laravel (REST API)
Database    : PostgreSQL
Queue       : Redis atau database queue
Auth        : Laravel Sanctum (token based)
Admin Web   : Laravel Blade / Filament
Storage     : VPS local storage atau S3-compatible
Scheduler   : Laravel Scheduler (cron) untuk reconcile & housekeeping
```

### 7.3 Infrastruktur

```text
Server      : VPS Linux (Ubuntu LTS disarankan)
Web server  : Nginx + PHP-FPM
Process mgr : Supervisor (queue worker), systemd/cron (scheduler)
TLS         : HTTPS wajib (Let's Encrypt / reverse proxy)
Backup      : Backup PostgreSQL terjadwal (lihat bagian 24)
```

---

## 8. MULTI-TENANT FOUNDATION

> **Aplikasi ini adalah POS SaaS MULTI-TENANT, BUKAN POS single toko.**

### 8.1 Aturan Wajib

```text
MT1. Setiap data operasional WAJIB memiliki kolom tenant_id.
MT2. Data yang bersifat per-toko WAJIB memiliki juga store_id.
MT3. User tenant A TIDAK BOLEH melihat data tenant B — tanpa pengecualian.
MT4. Setiap query backend WAJIB otomatis ter-scope ke tenant aktif.
MT5. tenant_id TIDAK BOLEH diambil dari input request; diambil dari token/user.
MT6. Super Admin adalah satu-satunya peran lintas-tenant.
```

### 8.2 Strategi Isolasi

```text
Strategi MVP : Shared database, shared schema, ROW-LEVEL tenant scoping.
Mekanisme    : Global Query Scope (Eloquent Global Scope) berbasis tenant_id.
Penegakan    : Middleware resolve tenant dari Sanctum token -> set tenant context.
Model        : Semua model operasional memakai trait "BelongsToTenant" yang:
               - otomatis mengisi tenant_id saat create,
               - otomatis menambahkan where tenant_id = ? saat query.
```

### 8.3 Hierarki Entitas

```text
tenant (1) ──< store (N)
tenant (1) ──< user (N)      (user terikat tenant; role menentukan akses)
tenant (1) ──< device (N)
store  (1) ──< sale (N)
tenant (1) ──< product (N)   (produk milik tenant, harga bisa per-store)
tenant (1) ──< subscription (1 aktif)
```

### 8.4 Test Isolasi (WAJIB)

```text
- Buat tenant A dan tenant B dengan data masing-masing.
- Login sebagai user tenant A.
- Pastikan SEMUA endpoint hanya mengembalikan data tenant A.
- Coba akses resource milik tenant B by ID -> WAJIB 403/404.
- Test ini WAJIB ada di suite otomatis dan lulus sebelum rilis (No-Go jika gagal).
```

---

## 9. MODUL BACKEND

```text
1.  Auth Module
    - Login, logout, token (Sanctum), refresh, me/profile.
    - Resolusi tenant & role dari token.

2.  Tenant Module
    - CRUD tenant (Super Admin).
    - Status tenant (active/suspended).

3.  Store Module
    - CRUD store per tenant.

4.  User Module
    - CRUD user per tenant, assign role (owner/cashier).

5.  Device Module
    - Registrasi device, aktivasi/deaktivasi, enforcement device limit.

6.  Catalog Module
    - Product categories, products, product_store_prices.

7.  Sales Module
    - Create sale, sale items, list, detail.
    - Idempotency untuk sync offline (client_uuid).

8.  Payment Module
    - Create payment (CASH langsung selesai; QRIS via gateway).
    - Provider abstraction (Midtrans/Xendit/Duitku/Manual).
    - Status: PENDING/PAID/FAILED/EXPIRED/CANCELLED.

9.  Webhook Module
    - Endpoint webhook per provider.
    - Verifikasi signature, idempotency, logging (payment_webhook_logs).
    - Update status payment & sale.

10. Reconciliation Module
    - Command: php artisan payments:reconcile --date=YYYY-MM-DD

11. Inventory Module (simple)
    - inventory_movements, stok sederhana.

12. Report Module
    - Daily closing, ringkasan penjualan harian per store.

13. Subscription Module
    - Paket, status, expiry, device limit, guardrail enforcement.

14. Audit Module
    - audit_logs untuk aksi sensitif (payment, subscription, device, auth).

15. Admin Web Module
    - Blade/Filament untuk Super Admin & Owner.
```

---

## 10. MODUL ANDROID

```text
1.  Auth & Session
    - Login ke tenant, simpan token aman, logout.

2.  Sync Engine
    - Pull produk/kategori/harga dari backend ke Room.
    - Push transaksi cash offline (WorkManager).

3.  Catalog (Local)
    - Tampilkan produk dari Room, pencarian cepat lokal.

4.  Cashier / Cart
    - Tambah item, ubah qty, diskon sederhana, hitung total.

5.  Checkout
    - CASH (bisa offline) & QRIS (online-only).

6.  QRIS Flow
    - Request QRIS ke backend, tampilkan QR, polling status.

7.  Printer
    - Pairing Bluetooth, cetak struk ESC/POS, reprint.

8.  Offline Queue
    - sync_queue lokal, status pending/synced/failed.

9.  Reports (ringan)
    - Ringkasan penjualan harian device / tutup kasir sederhana.

10. Settings
    - app_settings: printer, device info, sync interval, dsb.
```

Aturan Android:

```text
AND1. QRIS WAJIB online. Jika offline, tombol QRIS dinonaktifkan.
AND2. CASH boleh offline; transaksi masuk sync_queue.
AND3. Semua penulisan uang final tetap divalidasi backend saat sync.
AND4. Produk selalu dibaca dari Room (cepat), bukan network saat kasir.
AND5. Tidak ada credential gateway di app.
```

---

## 11. DATABASE FOUNDATION

### 11.1 Entitas Backend (PostgreSQL) — Minimal

```text
tenants                 : master tenant (SaaS customer).
stores                  : toko milik tenant.
users                   : user milik tenant (owner/cashier). Punya tenant_id.
devices                 : device terdaftar per tenant (untuk device limit).
product_categories      : kategori produk (tenant_id).
products                : produk (tenant_id).
product_store_prices    : harga produk per store (tenant_id, store_id, product_id).
sales                   : transaksi penjualan (tenant_id, store_id, device, client_uuid).
sale_items              : item per transaksi (tenant_id, sale_id, product_id).
payments                : pembayaran per sale (tenant_id, method, status, provider).
payment_webhook_logs    : log mentah + hasil verifikasi webhook gateway.
inventory_movements      : pergerakan stok (tenant_id, store_id, product_id).
daily_closings          : tutup kasir / ringkasan harian (tenant_id, store_id).
subscriptions           : paket & status langganan per tenant.
audit_logs              : jejak audit aksi sensitif.
```

### 11.2 Kolom Wajib (Konvensi)

```text
- Semua tabel operasional     : tenant_id (NOT NULL, indexed).
- Tabel per-store             : store_id (NOT NULL, indexed).
- Timestamps                  : created_at, updated_at.
- Soft delete bila relevan    : deleted_at.
- Idempotency transaksi sync  : sales.client_uuid (UNIQUE per tenant).
- Uang                        : simpan sebagai integer (rupiah, tanpa desimal) atau
                                decimal(15,2). Konsisten satu keputusan (default: integer rupiah).
```

### 11.3 Sketsa Kolom Kunci (Referensi Desain, BUKAN Migrasi)

```text
tenants(id, name, status, created_at, updated_at)
stores(id, tenant_id, name, address, is_active, created_at, updated_at)
users(id, tenant_id, name, email, password, role, is_active, created_at, updated_at)
devices(id, tenant_id, store_id, device_uid, label, is_active, last_seen_at, created_at)
product_categories(id, tenant_id, name, sort_order, created_at)
products(id, tenant_id, category_id, sku, name, base_price, is_active, image_thumb, created_at)
product_store_prices(id, tenant_id, store_id, product_id, price, created_at)
sales(id, tenant_id, store_id, device_id, client_uuid, cashier_id, subtotal, discount,
      total, status, source[online|offline], created_at, synced_at)
sale_items(id, tenant_id, sale_id, product_id, name_snapshot, price, qty, line_total)
payments(id, tenant_id, sale_id, method[CASH|QRIS], provider[MIDTRANS|XENDIT|DUITKU|MANUAL],
         amount, status[PENDING|PAID|FAILED|EXPIRED|CANCELLED], provider_ref,
         qr_payload, expires_at, paid_at, created_at, updated_at)
payment_webhook_logs(id, tenant_id, provider, payment_id, raw_payload, signature_valid,
                     event_type, processed, created_at)
inventory_movements(id, tenant_id, store_id, product_id, type[IN|OUT|ADJUST], qty, ref, created_at)
daily_closings(id, tenant_id, store_id, business_date, total_cash, total_qris, total_sales,
               opened_at, closed_at, cashier_id)
subscriptions(id, tenant_id, plan[STARTER|STANDARD|PRO], status[ACTIVE|EXPIRED|SUSPENDED],
              device_limit, started_at, expires_at, created_at, updated_at)
audit_logs(id, tenant_id, user_id, action, entity, entity_id, meta, ip, created_at)
```

### 11.4 Local Database Android (Room) — Minimal

```text
local_products     : cache produk (id server, tenant, kategori, nama, harga, thumb).
local_categories   : cache kategori.
local_sales        : transaksi lokal (client_uuid, total, status sync).
local_sale_items   : item transaksi lokal.
local_payments     : pembayaran lokal (CASH; QRIS hanya jika sudah online).
sync_queue         : antrean sync (entity, ref_uuid, status, retry_count, last_error).
app_settings       : pengaturan aplikasi (printer, device_uid, sync interval, base url).
```

---

## 12. API FOUNDATION

### 12.1 Konvensi

```text
- Base path       : /api/v1
- Format          : JSON.
- Auth            : Authorization: Bearer <sanctum_token>.
- Tenant scoping  : otomatis dari token; TIDAK menerima tenant_id dari body.
- Waktu           : ISO 8601 UTC.
- Error format    : { "error": { "code": "...", "message": "...", "details": {...} } }
- Idempotency     : sales pakai client_uuid; webhook pakai provider event id.
- Versioning      : path-based (/v1).
```

### 12.2 Endpoint Inti (MVP)

```text
Auth
  POST   /api/v1/auth/login
  POST   /api/v1/auth/logout
  GET    /api/v1/auth/me

Device
  POST   /api/v1/devices/register
  POST   /api/v1/devices/heartbeat

Catalog (Android pull)
  GET    /api/v1/catalog/sync?since=<timestamp>     (produk+kategori+harga delta)

Sales
  POST   /api/v1/sales                              (idempotent via client_uuid)
  GET    /api/v1/sales/{id}
  GET    /api/v1/sales?date=YYYY-MM-DD

Payments
  POST   /api/v1/payments/qris                      (buat QRIS dinamis)
  GET    /api/v1/payments/{id}/status               (polling status)
  POST   /api/v1/payments/{id}/cancel

Webhooks (server-to-server, tanpa Sanctum, verifikasi signature)
  POST   /api/v1/webhooks/midtrans
  POST   /api/v1/webhooks/xendit
  POST   /api/v1/webhooks/duitku

Reports
  GET    /api/v1/reports/daily?date=YYYY-MM-DD&store_id=<id>
  POST   /api/v1/closings                           (tutup kasir)
```

### 12.3 Aturan Keamanan API

```text
API1. Semua endpoint operasional wajib melewati middleware auth + tenant scope.
API2. Endpoint webhook TIDAK memakai Sanctum; memakai verifikasi signature provider.
API3. Rate limiting pada auth & payment endpoints.
API4. Tidak ada endpoint yang mengembalikan data lintas-tenant.
API5. Semua aksi uang menulis audit_logs.
```

---

## 13. QRIS PAYMENT FOUNDATION

### 13.1 Aturan Mutlak

```text
Q1. Android TIDAK BOLEH memanggil payment gateway secara langsung.
Q2. Android hanya request QRIS ke BACKEND.
Q3. BACKEND yang membuat payment ke payment gateway.
Q4. Webhook gateway masuk ke BACKEND.
Q5. BACKEND memvalidasi webhook (signature + idempotency).
Q6. BACKEND meng-update status payment.
Q7. Android hanya polling / refresh status payment.
Q8. Credential/API key gateway HANYA ada di backend (env server), TIDAK di Android.
```

### 13.2 Status QRIS (Minimal)

```text
PENDING     : QRIS dibuat, menunggu pembayaran.
PAID        : Pembayaran berhasil (dikonfirmasi via webhook / verifikasi).
FAILED      : Pembayaran gagal.
EXPIRED     : QRIS kadaluarsa sebelum dibayar.
CANCELLED   : Dibatalkan kasir/sistem.
```

### 13.3 Payment Method (Minimal)

```text
CASH        : tunai (boleh offline).
QRIS        : QRIS dinamis via gateway (online-only).
```

### 13.4 Provider yang Disiapkan

```text
MIDTRANS    : provider.
XENDIT      : provider.
DUITKU      : provider.
MANUAL      : mode manual (konfirmasi manual / testing / fallback tanpa gateway).
```

Arsitektur provider:

```text
- Backend memiliki interface "PaymentProvider" dengan method:
  createQris(amount, ref) -> {provider_ref, qr_payload, expires_at}
  verifyWebhook(request)  -> {valid, event, provider_ref, status}
- Setiap provider (Midtrans/Xendit/Duitku/Manual) mengimplementasi interface ini.
- Pemilihan provider per-tenant disimpan di konfigurasi tenant (server-side).
```

### 13.5 Alur QRIS

```text
1. Kasir pilih QRIS -> Android POST /payments/qris (sale_id, amount).
2. Backend cek: tenant aktif? online? -> buat payment PENDING + panggil gateway.
3. Gateway kembalikan qr_payload + expires_at -> backend simpan -> balas ke Android.
4. Android tampilkan QR. Pelanggan bayar.
5. Gateway kirim webhook -> backend verifikasi signature -> update payment PAID.
6. Android polling GET /payments/{id}/status -> lihat PAID -> selesaikan sale -> cetak struk.
7. Jika expired/failed -> Android tampilkan pesan, kasir bisa retry / ganti ke CASH.
```

### 13.6 Rekonsiliasi (WAJIB)

```text
Command:
  php artisan payments:reconcile --date=YYYY-MM-DD

Fungsi:
- Ambil semua payment QRIS dengan status non-final (PENDING) pada tanggal tsb.
- Query status aktual ke gateway (atau cocokkan dengan settlement report).
- Perbaiki status yang tertinggal (mis. PAID yang webhook-nya gagal masuk).
- Tulis hasil ke audit_logs.
- Dijalankan terjadwal (harian) + bisa manual saat insiden.
```

---

## 14. OFFLINE & SYNC FOUNDATION

### 14.1 Aturan Mutlak

```text
O1. Transaksi CASH BOLEH offline.
O2. Transaksi QRIS TIDAK BOLEH offline (butuh koneksi & gateway).
O3. Produk disimpan di local database Android (Room).
O4. Transaksi cash offline masuk sync_queue.
O5. Saat online, WorkManager sync transaksi ke backend.
O6. Sync WAJIB idempotent (client_uuid) agar tidak dobel.
O7. Data transaksi offline TIDAK BOLEH hilang sebelum sync sukses dikonfirmasi.
```

### 14.2 Local DB Android (Ulang, Referensi)

```text
local_products, local_categories, local_sales, local_sale_items,
local_payments, sync_queue, app_settings
```

### 14.3 Mekanisme Sync

```text
- Pull katalog : GET /catalog/sync?since=... -> upsert ke Room.
- Push sales   : WorkManager job ambil sync_queue (status=pending) ->
                 POST /sales dengan client_uuid ->
                 sukses: tandai synced, simpan server_id ->
                 gagal jaringan: retry dengan backoff ->
                 gagal validasi (4xx non-retry): tandai failed + log, tampilkan ke owner.
- Idempotency  : jika client_uuid sudah ada di server, server balas record yang sama (200).
- Konflik      : uang menang di sisi backend; Android menyesuaikan status akhir.
```

### 14.4 Aturan Kualitas Offline

```text
- Retry dengan exponential backoff + batas percobaan.
- sync_queue menyimpan retry_count & last_error untuk diagnosa.
- Owner bisa melihat jumlah transaksi pending & failed.
- Tidak boleh ada penghapusan otomatis transaksi failed tanpa jejak.
```

---

## 15. RECEIPT PRINTER FOUNDATION

```text
Teknologi  : Bluetooth ESC/POS (thermal printer 58mm/80mm umum di UMKM).
Lib        : ESC/POS command via Bluetooth (native Android BT).

Aturan:
R1. Printer WAJIB stabil sebelum rilis (No-Go jika sering gagal).
R2. Pairing printer disimpan di app_settings.
R3. Struk memuat: nama toko, alamat, tanggal/jam, no transaksi, item, qty, harga,
    subtotal, diskon, total, metode bayar, status (LUNAS/QRIS PAID), footer.
R4. Fitur REPRINT struk terakhir wajib ada.
R5. Cetak struk maksimal 5 detik (lihat target performa).
R6. Kegagalan cetak tidak boleh membatalkan transaksi yang sudah final.
R7. Struk QRIS hanya dicetak "PAID" setelah status PAID terkonfirmasi.
```

---

## 16. SECURITY FOUNDATION

```text
S1.  Tidak ada credential payment gateway di Android (kode/resource/APK).
S2.  Semua API key gateway hanya di env server backend.
S3.  Semua komunikasi Android <-> Backend via HTTPS.
S4.  Auth via Sanctum token; token disimpan aman di Android (EncryptedSharedPrefs).
S5.  Tenant scoping otomatis; tidak menerima tenant_id dari client.
S6.  Webhook diverifikasi signature + idempotency; log ke payment_webhook_logs.
S7.  Rate limiting pada login & payment endpoints.
S8.  Audit log untuk aksi sensitif (payment, subscription, device, auth).
S9.  Password hashing (bcrypt/argon) di backend.
S10. Prinsip least privilege pada role (cashier tidak bisa aksi owner/admin).
S11. Input validation server-side untuk semua endpoint.
S12. Backup database terenkripsi & teruji restore (lihat bagian 24).
```

---

## 17. PERFORMANCE FOUNDATION

Target performa (WAJIB, diuji di HP lawas):

```text
Buka aplikasi          : maksimal 3 detik
Buka halaman kasir     : maksimal 1 detik
Cari produk lokal      : maksimal 300 ms
Tambah item ke cart    : instan (< 100 ms terasa instan)
Checkout cash          : maksimal 1 detik
Generate QRIS          : maksimal 5 detik (tergantung gateway)
Cetak struk            : maksimal 5 detik
```

Aturan performa:

```text
PF1. Semua target diverifikasi di HP lawas, bukan hanya emulator kelas atas.
PF2. Pencarian produk memakai index Room + query lokal, bukan network.
PF3. Gambar produk = thumbnail kecil, di-cache, lazy load.
PF4. Hindari layout dalam & over-draw; gunakan RecyclerView efisien.
PF5. Startup ringan: tidak ada kerja berat di UI thread saat launch.
PF6. Aplikasi harus tetap nyaman digunakan di HP lawas (RAM & CPU rendah).
```

---

## 18. UI/UX FOUNDATION

```text
UX1. Fokus kecepatan kasir: alur pilih produk -> bayar -> cetak minimal langkah.
UX2. Tombol besar, teks jelas, kontras baik (dipakai sambil berdiri/antri).
UX3. Native XML/Views, bukan WebView.
UX4. Indikator status online/offline selalu terlihat.
UX5. QRIS dinonaktifkan (disabled) secara visual saat offline.
UX6. Feedback jelas untuk sukses/gagal (checkout, cetak, sync).
UX7. Bahasa Indonesia sebagai default.
UX8. Minim animasi; utamakan responsif.
UX9. Layout adaptif untuk HP dan tablet.
```

---

## 19. ADMIN WEB FOUNDATION

```text
Teknologi : Laravel Blade / Filament.

Peran & fungsi:
- SUPER ADMIN (SaaS):
  * CRUD tenant.
  * Atur subscription (paket, status, expiry, device limit).
  * Lihat & kelola devices per tenant.
  * Monitoring pembayaran & webhook logs.
  * Jalankan/pantau rekonsiliasi.

- OWNER (tenant):
  * CRUD produk, kategori, harga per store.
  * CRUD user (cashier) dalam tenant.
  * Lihat laporan harian per store.
  * Kelola store.

Aturan:
AW1. Admin web juga wajib tenant-scoped untuk owner.
AW2. Super Admin adalah satu-satunya lintas-tenant.
AW3. Aksi sensitif tercatat di audit_logs.
```

---

## 20. SUBSCRIPTION GUARDRAIL

### 20.1 Paket

```text
Starter    : entry level (device limit kecil, 1 store).
Standard   : menengah (lebih banyak device/user, laporan).
Pro        : multi-store, device lebih banyak, fitur lanjutan.
```

### 20.2 Aturan Subscription Expired

```text
SUB1. Subscription EXPIRED -> TIDAK BOLEH membuat transaksi baru.
SUB2. Subscription EXPIRED -> TETAP boleh sync transaksi pending (data tidak hilang).
SUB3. Subscription EXPIRED -> Owner TETAP boleh login untuk perpanjangan/export.
```

### 20.3 Aturan Device Limit

```text
DEV1. Setiap paket punya device_limit.
DEV2. Jika paket hanya 1 device, device kedua TIDAK BOLEH aktif
      kecuali admin menonaktifkan device lama.
DEV3. Aktivasi device di atas limit -> ditolak backend dengan pesan jelas.
DEV4. Deaktivasi device dilakukan oleh admin/owner (sesuai kebijakan).
```

### 20.4 Penegakan (Enforcement)

```text
- Enforcement guardrail dilakukan di BACKEND (bukan hanya UI Android).
- Endpoint create sale & register device mengecek status subscription & device limit.
- Android menampilkan pesan yang ramah saat ditolak, tapi kebenaran ada di backend.
```

---

## 21. MVP SCOPE

### 21.1 Termasuk MVP (IN SCOPE)

```text
- Multi-tenant + isolasi data.
- Login Android ke tenant, role owner/cashier.
- Admin web: buat tenant (super admin), CRUD produk (owner).
- Sync produk ke Android.
- Transaksi CASH (online & offline) + sync.
- Transaksi QRIS dinamis (online) via gateway + webhook.
- Cetak struk Bluetooth ESC/POS + reprint.
- Laporan harian sederhana + tutup kasir.
- Subscription guardrail (expired) + device limit.
- Backup database.
- Audit log payment.
- Rekonsiliasi QRIS (command).
```

### 21.2 TIDAK Termasuk MVP (OUT OF SCOPE)

```text
- Pembayaran langganan otomatis (billing SaaS otomatis).
- Multi-store kompleks / transfer stok antar store.
- Loyalty / membership / poin.
- Diskon/promosi kompleks (hanya diskon sederhana).
- E-commerce / online order.
- Analitik lanjutan / dashboard BI.
- Integrasi akuntansi eksternal.
```

---

## 22. SPRINT ROADMAP

Setiap sprint memiliki **Output** dan **GO criteria**. Sprint dianggap selesai hanya
jika GO criteria terpenuhi.

### Sprint 0 — Project Setup

```text
Output:
- Struktur repo (backend, android, docs).
- Setup Laravel + PostgreSQL + Sanctum skeleton.
- Setup Android Kotlin skeleton (Room, Retrofit, WorkManager).
- CI dasar & environment config (tanpa secret di repo).
GO criteria:
- Backend "hello" endpoint jalan dengan auth skeleton.
- Android build sukses & bisa hit endpoint sehat.
- Dokumen foundation ini menjadi acuan resmi.
```

### Sprint 1 — SaaS Tenant Foundation

```text
Output:
- Tabel tenants, stores, users, devices.
- Middleware tenant scoping + trait BelongsToTenant.
- Auth (login/logout/me) dengan tenant & role.
- Test isolasi tenant otomatis.
GO criteria:
- 1 aplikasi bisa login ke tenant berbeda.
- User tenant A tidak bisa melihat data tenant B (test lulus).
```

### Sprint 2 — Product Foundation

```text
Output:
- product_categories, products, product_store_prices.
- Admin web (owner) CRUD produk/kategori/harga.
- Endpoint catalog sync.
GO criteria:
- Produk dibuat dari admin web tersimpan tenant-scoped.
- Endpoint catalog/sync mengembalikan hanya produk tenant tsb.
```

### Sprint 3 — Android Cashier Foundation

```text
Output:
- Pull katalog ke Room.
- Halaman kasir: daftar produk, pencarian lokal, cart.
GO criteria:
- Android sync produk dari backend.
- Cari produk lokal < 300 ms, tambah item instan (HP lawas).
```

### Sprint 4 — Sales Backend Integration

```text
Output:
- sales, sale_items + endpoint POST /sales idempotent (client_uuid).
- Checkout CASH online dari Android.
GO criteria:
- Kasir membuat transaksi cash online -> tersimpan di backend.
- Kirim ulang dengan client_uuid sama tidak menggandakan transaksi.
```

### Sprint 5 — QRIS Payment Gateway

```text
Output:
- Payment module + provider interface (MANUAL + minimal 1 real: Midtrans/Xendit/Duitku).
- Endpoint /payments/qris, /payments/{id}/status.
- Webhook endpoint + verifikasi signature + payment_webhook_logs.
GO criteria:
- Kasir membuat transaksi QRIS -> QR tampil.
- QRIS berubah PAID dari webhook (bukan dari Android).
- Tidak ada API key gateway di Android.
```

### Sprint 6 — Printer & Receipt

```text
Output:
- Pairing Bluetooth ESC/POS, cetak struk, reprint.
GO criteria:
- Struk tercetak stabil < 5 detik.
- Reprint struk terakhir berfungsi.
- Kegagalan cetak tidak membatalkan transaksi final.
```

### Sprint 7 — Offline Cash & Sync

```text
Output:
- Room: local_sales, local_sale_items, local_payments, sync_queue.
- WorkManager sync engine + retry/backoff.
GO criteria:
- Transaksi cash dibuat saat offline.
- Saat online, transaksi tersync tanpa duplikasi & tanpa kehilangan data.
```

### Sprint 8 — Inventory Simple

```text
Output:
- inventory_movements + pengurangan stok saat penjualan (sederhana).
GO criteria:
- Stok berkurang benar per store saat transaksi.
- Pergerakan stok tercatat & tenant-scoped.
```

### Sprint 9 — Reports & Closing

```text
Output:
- daily_closings + endpoint laporan harian + tutup kasir.
GO criteria:
- Owner melihat laporan harian yang AKURAT (cash + qris) per store.
- Tutup kasir menghasilkan ringkasan yang benar.
```

### Sprint 10 — Subscription & Device Limit

```text
Output:
- subscriptions + guardrail expired + device limit enforcement.
- Admin (super admin) atur paket/status/expiry/device limit.
GO criteria:
- Subscription expired memblokir transaksi baru, tapi sync pending & login owner tetap jalan.
- Device kedua ditolak saat limit 1 device kecuali device lama dinonaktifkan.
```

### Sprint 11 — Pilot Hardening

```text
Output:
- Backup database terjadwal & teruji restore.
- Command rekonsiliasi QRIS + scheduler.
- Audit log payment lengkap.
- Uji performa HP lawas & stabilitas printer.
GO criteria:
- Semua Definition of Done MVP terpenuhi.
- Tidak ada pelanggaran No-Go Rules.
```

---

## 23. TESTING FOUNDATION

```text
T1.  Test isolasi tenant otomatis (WAJIB, No-Go jika gagal).
T2.  Test idempotency sales (client_uuid tidak menggandakan).
T3.  Test webhook: signature valid/invalid, idempotency event.
T4.  Test status transition payment (PENDING->PAID/EXPIRED/FAILED/CANCELLED).
T5.  Test subscription guardrail (expired memblokir create sale, sync tetap jalan).
T6.  Test device limit (tolak device di atas limit).
T7.  Test offline sync (buat offline -> online -> sync tanpa loss/duplikasi).
T8.  Test laporan harian akurat (cash+qris cocok dengan transaksi).
T9.  Test performa manual di HP lawas terhadap target bagian 17.
T10. Test printer manual (cetak, reprint, gagal cetak tidak batalkan transaksi).

Level test:
- Backend: unit + feature test (PHPUnit/Pest).
- Android: unit (ViewModel/Repository) + instrumented test kritikal (Room/sync).
- Manual QA: printer, HP lawas, alur QRIS end-to-end.
```

---

## 24. DEPLOYMENT FOUNDATION

```text
D1.  Backend deploy di VPS: Nginx + PHP-FPM + PostgreSQL + Redis.
D2.  Queue worker via Supervisor; scheduler via cron (php artisan schedule:run).
D3.  HTTPS wajib (TLS).
D4.  Environment secrets hanya di server (.env), TIDAK di repo, TIDAK di Android.
D5.  Backup PostgreSQL terjadwal (mis. pg_dump harian) + retensi + uji restore.
D6.  Rekonsiliasi QRIS terjadwal (harian) via scheduler.
D7.  Android rilis via Play Store; targetSDK mengikuti requirement Play.
D8.  Rollback plan: migrasi reversible / backup sebelum deploy besar.
D9.  Monitoring dasar: log error backend, log webhook, health check.

Backup (WAJIB):
- pg_dump terjadwal + simpan aman (offsite/S3-compatible).
- Uji restore secara berkala (backup yang tidak teruji = tidak dianggap ada).
```

---

## 25. NO-GO RULES

> Aplikasi **TIDAK BOLEH** dianggap siap dijual jika salah satu kondisi berikut terjadi.

```text
NG1.  Data tenant bisa bocor (isolasi tenant gagal).
NG2.  QRIS belum webhook-based (status bergantung pada Android, bukan webhook backend).
NG3.  Payment API key ada di Android.
NG4.  Transaksi cash offline sering hilang.
NG5.  Printer belum stabil.
NG6.  Laporan harian tidak akurat.
NG7.  Device limit belum ada.
NG8.  Subscription expired belum dibatasi (masih bisa transaksi baru).
NG9.  Tidak ada backup database.
NG10. Tidak ada audit log payment.
NG11. Tidak ada cara rekonsiliasi QRIS.
```

Jika salah satu di atas benar → **STOP. TIDAK GO.** Perbaiki dulu.

---

## 26. DEFINITION OF DONE MVP

> MVP dianggap **SELESAI** hanya jika **SEMUA** poin berikut terpenuhi.

```text
1.  1 aplikasi Android bisa login ke tenant berbeda.
2.  Setiap tenant hanya melihat datanya sendiri.
3.  Produk bisa dibuat dari admin web.
4.  Android bisa sync produk.
5.  Kasir bisa membuat transaksi cash.
6.  Kasir bisa membuat transaksi QRIS.
7.  QRIS berubah PAID dari webhook.
8.  Struk bisa dicetak.
9.  Transaksi cash bisa dibuat offline.
10. Transaksi offline bisa sync saat online.
11. Owner bisa melihat laporan harian.
12. Admin SaaS bisa membuat tenant baru.
13. Admin SaaS bisa mengatur subscription.
14. Device limit berjalan.
15. Backup database berjalan.
16. Tidak ada API key sensitif di Android.
17. Aplikasi nyaman digunakan di HP lawas.
```

---

## 27. STRUKTUR REPOSITORY

Struktur target (referensi; dibangun bertahap sesuai sprint):

```text
pos_app/
├── README.md
├── docs/
│   ├── PROJECT_RULES.md
│   └── foundation/
│       └── POS_ANDROID_SAAS_FOUNDATION.md      <-- DOKUMEN KUNCI (ini)
├── backend/                                    (Laravel API)
│   ├── app/
│   │   ├── Http/{Controllers,Middleware}/
│   │   ├── Models/
│   │   ├── Services/Payments/                  (provider abstraction)
│   │   └── Console/Commands/                   (payments:reconcile, dsb)
│   ├── database/{migrations,seeders}/
│   ├── routes/api.php
│   └── tests/
└── android/                                    (Kotlin native)
    ├── app/src/main/java/.../
    │   ├── data/{room,remote,repository}/
    │   ├── domain/
    │   ├── ui/{cashier,checkout,printer,settings}/
    │   └── sync/                               (WorkManager)
    └── app/src/main/res/
```

Aturan repo:

```text
RP1. docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md adalah source of truth.
RP2. Backend & Android dipisah folder jelas (mono-repo atau multi-repo sesuai keputusan tim).
RP3. Tidak ada secret di repo (.env di-gitignore).
RP4. Setiap PR menyebut sprint & GO criteria yang dipenuhi.
```

---

## 28. PRIORITAS EKSEKUSI

Urutan eksekusi yang direkomendasikan (mengikuti roadmap, fokus mengurangi risiko lebih dulu):

```text
Prioritas 1 (Fondasi tidak bisa ditawar):
  - Multi-tenant + isolasi data (Sprint 1).
  - Auth & role (Sprint 1).

Prioritas 2 (Nilai inti kasir):
  - Produk + katalog sync (Sprint 2).
  - Kasir Android + cart (Sprint 3).
  - Sales cash online + idempotency (Sprint 4).

Prioritas 3 (Uang & kepercayaan):
  - QRIS gateway + webhook + audit (Sprint 5).
  - Printer & struk (Sprint 6).

Prioritas 4 (Ketahanan lapangan):
  - Offline cash & sync (Sprint 7).
  - Inventory simple (Sprint 8).
  - Laporan & tutup kasir (Sprint 9).

Prioritas 5 (Kesiapan komersial SaaS):
  - Subscription guardrail & device limit (Sprint 10).
  - Backup, rekonsiliasi, hardening, uji HP lawas (Sprint 11).
```

Aturan prioritas:

```text
PR1. Jangan lanjut ke prioritas berikutnya jika GO criteria sebelumnya belum lulus.
PR2. Isolasi tenant & keamanan payment tidak boleh ditunda demi fitur.
PR3. Setiap keputusan yang menyimpang dari dokumen ini WAJIB memperbarui dokumen ini dulu.
```

---

> **AKHIR DOKUMEN FOUNDATION.**
> Dokumen ini adalah **foundation lock** proyek Aish POS Lite.
> Semua implementasi wajib merujuk dan tunduk pada dokumen ini.
