# UIX-8C-07 — Premium Auth, Activation, Session Recovery & Settings

The startup, device-activation, cashier-login, session-recovery, and settings
surfaces are rebuilt to a premium, truthful, accessible standard on the UIX-8C-02
design system (Material 3 tokens, `Widget.Aish.*`, `TextAppearance.Aish.*`). These
screens **present** the resolved `BootState` and `RuntimeContext`; they never compute
trust, money, or sync state locally (UIX8C-R245..R247). All copy below is the
governed Indonesian intent copy.

## Information hierarchy (every screen)

Every auth/recovery/settings surface follows one hierarchy top-to-bottom:

1. **Primary status** — what state the app is in (one line, text + icon, never colour
   alone).
2. **Tenant / outlet / cashier context** — who and where, from `RuntimeContext`
   (missing value → "Tidak tersedia").
3. **Primary action** — the single most likely next step (a `Widget.Aish.Button`
   primary CTA).
4. **Help / recovery** — the governed way out (re-activate, contact, retry).
5. **Secondary technical detail** — reference ids, timestamps, build info; visually
   subordinate, never a secret.

## Splash / startup progress

- Brand splash with the sparing gradient accent (header/CTA only, never a full-fill).
- A single indeterminate progress + a truthful status line mapped from the transient
  `BootState`:
  - `Bootstrapping` / `DatabaseMigration` → **"Menyiapkan aplikasi…"**
  - `RestoringRuntime` → **"Memulihkan sesi…"** (no login flash while restorable,
    UIX8C-R214).
- Bounded: if the startup budget is exceeded the screen resolves to a retry state
  (**"Gagal memuat. Coba lagi."** with a Retry button), never an infinite spinner
  (UIX8C-R212).

## Device activation

- **Primary status:** "Aktivasi perangkat diperlukan."
- **Context:** outlet/tenant name once known, else "Tidak tersedia".
- **Primary action:** activation-code entry + **"Aktifkan perangkat"**.
- **States & copy:**
  - In flight (`ActivatingDevice`) → "Mengaktifkan perangkat…", CTA disabled/guarded.
  - Expired code → "Kode aktivasi sudah kedaluwarsa. Minta kode baru."
  - Invalid/malformed → "Kode aktivasi tidak valid."
  - Already used / wrong tenant → "Kode aktivasi tidak dapat digunakan pada perangkat ini."
  - Offline → "Tidak ada koneksi. Aktivasi memerlukan koneksi internet." (no fabricated success)
- Activation code and installation id are never displayed as secrets; the installation
  id, if shown, is a truncated non-sensitive reference only.

## Cashier login

- **Primary status:** "Masuk sebagai kasir."
- **Context:** business + outlet name (from the activated device), else "Tidak tersedia".
- **Primary action:** credentials + **"Masuk"** (guarded while `Authenticating`).
- **States & copy:**
  - Invalid credentials (401) → "Email atau kata sandi salah." (generic, no user enumeration)
  - Locked → "Akun terkunci. Hubungi admin outlet Anda."
  - Wrong outlet → "Akun ini tidak terdaftar pada outlet perangkat ini."
  - Offline → "Tidak ada koneksi. Masuk memerlukan koneksi internet."

## Session expired

- **Primary status:** **"Sesi Anda telah berakhir. Silakan masuk kembali."**
- **Context:** the same tenant/outlet/cashier remains shown — the identity is not
  wiped, only re-authenticated.
- **Assurance line (when pending exist):** **"Transaksi yang belum tersinkron tetap
  tersimpan."** — reinforcing UIX8C-R233 (unsynced preserved through re-auth).
- **Primary action:** **"Masuk kembali"** → returns to cashier home on success with no
  data loss.

## Revoked device

- **Primary status (locked):** **"Perangkat ini telah dinonaktifkan. Hubungi admin
  Anda."**
- **Reason line (when the server supplies one):** shown verbatim from
  `device/status.reason`, no infrastructure detail.
- **Pending assurance:** **"Transaksi yang belum tersinkron tetap tersimpan dan
  dikarantina."** — the queue is quarantined, not deleted (UIX8C-R234).
- **No cashier controls** are reachable: no back-press, deep link, or restart exits
  the lock (UIX8C-R219). The only affordance is a governed re-activation entry point
  for an authorized operator.

## Logout blocked by unsynced work

- Triggered when a logout / account switch / tenant reset is attempted with
  `pendingUnsyncedCount > 0`.
- **Primary status:** **"Ada {n} transaksi yang belum tersinkron. Selesaikan sinkronisasi
  sebelum keluar."**
- **Affordances (UIX8C-R232):** **"Sinkronkan sekarang"** (governed manual retry, reuses
  UIX-8C-05 recovery) and **"Lihat transaksi tertunda"** (opens the pending list). The
  destructive action stays **blocked** — never a "logout anyway that deletes" path
  (UIX8C-R229).

## Settings

A premium sectioned settings surface; every value is truthful, sourced from
`RuntimeContext` / canonical reads, and never a fabricated placeholder. No section
renders a secret (token, activation token, credential) (UIX8C-R246).

| Section | Contents (truthful, read-only) |
|---------|-------------------------------|
| **Account / Context** | Cashier name, tenant/business name, outlet name. Missing → "Tidak tersedia". |
| **Device** | Device/activation status (Aktif / Dinonaktifkan), installation reference (non-sensitive), last status check time. |
| **Application** | App version name/code, build variant, environment label. |
| **Connection** | Connectivity + reachability, truthfully distinct (Online / Terhubung tetapi server tidak terjangkau / Offline). |
| **Sync** | Pending count, last successful sync time, sync state (Tertunda / Menyinkronkan / Tersinkron / Gagal). |
| **Printer** | Printer configured/paired state + last outcome (reuses the UIX-8C-06 typed printer state). |
| **Security / Session** | Session status + a governed **"Keluar"** action (subject to the unsynced logout gate) and **"Ganti akun"** / **"Setel ulang perangkat"** (governed cleanup). |

Truthfulness rules: an unavailable value is **"Tidak tersedia"**, never `0`, never a
guessed default (UIX8C-R245); status labels are text (never colour alone,
UIX8C-R244); the sync/connection/printer values map from their canonical source of
truth, not a local guess (UIX8C-R242/R243).

## Accessibility (UIX8C-R248)

- All interactive targets (activation CTA, login fields, retry, sync-now, logout,
  section rows) are **≥48dp**.
- **Focus order** follows the hierarchy: primary status → context → primary action →
  help/recovery → secondary detail.
- **TalkBack:** every control has a meaningful accessible name; status, offline,
  session-expired, revoked, and blocked-logout states are announced, not colour-coded
  only. Icon-only controls carry content descriptions.
- **Font scale 100/115/130%:** the auth, activation, session-recovery, and settings
  surfaces keep their primary action visible or scroll-reachable at 130%; the CTA is
  never pushed off-screen (structural test in `AuthDeviceLayoutTest`; on-device
  visual confirmation is operator-observed and deferred, never fabricated).
- **Never colour-alone:** every status pairs an icon/text label with any colour cue.

## Reuse (no second engine)

These surfaces present `BootState`, `RuntimeContext`, `DeviceStatusMapper` output, the
`OfflineSaleRepository` pending count, and the UIX-8C-05/06 sync + printer states.
They add no pricing, payment, QRIS, settlement, or sync logic and use the single
canonical money formatter where money appears (UIX8C-R247). No hardcoded hex or `dp`
type sizes; all tokens come from the design system.
