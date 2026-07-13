# 55 — Android Cashier Experience (UIX-7)

The Android Cashier app (`com.aishtech.poslite`) is the tenant/device-scoped
point-of-sale surface a merchant's cashier actually uses. UIX-7 is a
**remediation of the cashier experience** — durability, financial integrity,
truthful state, security, accessibility, performance — over the existing
Android and backend domain services. It is **not** a feature expansion and it
never becomes a second pricing, payment, QRIS, settlement, or sync engine.

The app is native Views/XML + Retrofit/OkHttp + Room + WorkManager +
ViewModel/LiveData. Business truth stays in the backend `App\Services\*`
domains (Sprint 1–36) and the app's canonical repositories/managers; the UI
presents and orchestrates only.

## Surface, identity & authorization
- UIX7-R001 — Android Cashier is a distinct authenticated application surface
  and never inherits Platform Admin (`/admin/*`) or Tenant Owner (`/owner/*`)
  web authorization. It authenticates with Sanctum API tokens, not a web guard.
- UIX7-R002 — Existing backend and Android domain services remain the canonical
  sources of truth (sales, pricing, entitlement, QRIS, settlement, sync).
- UIX7-R003 — UI, ViewModel, Presenter, and local adapters must not duplicate
  pricing, tax, discount, entitlement, payment, QRIS, settlement, or sync
  business logic.
- UIX7-R004 — Tenant, outlet, user, and device context must be resolved from
  authenticated canonical state (session/token/device identity).
- UIX7-R005 — Raw client-supplied tenant or outlet identifiers are never trusted
  as authorization.

## Local data isolation
- UIX7-R006 — Local database, cache, files, preferences, and background work
  must be tenant/device/user scoped. Cloud/adb backup of tokens and the
  tenant-scoped Room DB is disabled (`allowBackup=false`).
- UIX7-R007 — Tenant A local or cached data must never be visible after
  authentication as Tenant B; account/device switching clears or re-scopes it.

## Offline durability & sync integrity
- UIX7-R008 — Offline transactions must be durably persisted before the UI
  presents a successful save state (cart cleared only after local save confirms).
- UIX7-R009 — Network availability is never required to preserve an otherwise
  valid offline transaction. An interrupted in-flight sync must be recoverable,
  never stranded and silently lost.
- UIX7-R010 — Sync retries must be idempotent (device-generated
  `clientReference`) and must not create duplicate server transactions.
- UIX7-R011 — Sync state, retry state, failure state, and conflict state must
  remain semantically distinct (PENDING / SYNCING / SYNCED / FAILED / CONFLICT).
- UIX7-R012 — Unknown or stale sync state must never be presented as synced; a
  row is SYNCED only on canonical server acknowledgement.
- UIX7-R013 — Conflict resolution must use canonical rules and must never
  silently overwrite authoritative transaction data.
- UIX7-R014 — Cart state must survive supported configuration changes and
  process recreation.
- UIX7-R015 — Checkout must prevent accidental double submission (ViewModel-level
  re-entry guard, not UI-only).
- UIX7-R016 — Logout, device reset, or account switching must not silently
  discard unsynced transactions; destructive cart actions are confirmed.
- UIX7-R017 — Device reactivation and account switching require explicit,
  governed handling of local tenant-scoped data.

## Financial integrity
- UIX7-R018 — Financial values use a canonical whole-rupiah integer
  representation (`core/money/RupiahMoney`, `Long`); unsafe float money
  calculations are forbidden in new/changed cashier code.
- UIX7-R019 — Cart totals, paid amounts, change, outstanding amount, and receipt
  values come from canonical calculations and are formatted only through the
  single canonical formatter (`RupiahMoney.format`).
- UIX7-R020 — QRIS created, pending, paid, confirmed, settlement pending,
  settled, failed, and expired states remain distinct.
- UIX7-R021 — QRIS creation alone must never be displayed as paid or settled.
- UIX7-R022 — Offline UI must not claim QRIS payment success without canonical
  confirmation; QRIS is online-only (`QrisOnlineOnlyGuard`).
- UIX7-R023 — Receipt and transaction history must display canonical, mutually
  consistent values.

## Truthful state & safe actions
- UIX7-R024 — Loading, unavailable, offline, pending, failed, retrying, conflict,
  and success states must be truthful; unavailable renders "Tidak tersedia",
  never a fabricated zero/success.
- UIX7-R025 — User actions that can create transactions must provide safe
  progress and duplicate-tap protection.
- UIX7-R026 — Android logs, analytics, crash reports, screenshots, and test
  artifacts must not contain credentials, tokens, private payment payloads, or
  unnecessary PII (the OkHttp logger redacts `Authorization` and runs in debug
  only).
- UIX7-R027 — Sensitive tokens and device credentials use the existing approved
  secure-storage mechanism; cleartext HTTP is denied by default
  (`network_security_config`), permitted only for local dev hosts.
- UIX7-R028 — Exported Android components, deep links, intents, and file sharing
  must be explicitly reviewed and least-privilege (only the launcher is exported).

## UI, accessibility & performance
- UIX7-R029 — UI must use the existing Aish POS design tokens
  (`res/values/colors.xml` etc.) and components; no hardcoded off-system colors.
- UIX7-R030 — Status must never rely on color alone (text label always present).
- UIX7-R031 — Interactive targets, TalkBack semantics, focus order, content
  descriptions, and font scaling are release gates.
- UIX7-R032 — Phone and tablet layouts must remain usable without clipped primary
  actions or inaccessible transaction totals.
- UIX7-R033 — Main-thread disk, database, and network I/O are forbidden (Room/
  Retrofit run on Dispatchers via coroutines/`viewModelScope`).
- UIX7-R034 — Background sync and polling must respect battery, network, retry,
  and platform scheduling constraints (WorkManager, network-constrained).
- UIX7-R035 — Aggressive unbounded polling is forbidden.
- UIX7-R036 — Crash, ANR, duplicate transaction, lost offline transaction, and
  cross-tenant leakage are automatic NO-GO conditions.
- UIX7-R037 — Performance budgets must use measured baseline data and must not be
  fabricated.

## Release, artifact & runtime governance
- UIX7-R038 — Android release artifacts must be traceable to source commit,
  package ID, version name, version code, variant, and hash.
- UIX7-R039 — Pilot runtime verification must use an installable artifact against
  HTTPS `aishpos.online` with synthetic data.
- UIX7-R040 — Synthetic accounts, devices, products, and transactions must be
  cleaned and cleanup must be verified.
- UIX7-R041 — Production Artisan operations must preserve PHP-FPM ownership of
  `storage/framework` and `bootstrap/cache`.
- UIX7-R042 — Composer `--no-dev` production verification must not rely on Faker
  or development-only packages.
- UIX7-R043 — Shared-VPS synchronization must not change or regress
  DaengtisiaMS.
- UIX7-R044 — GO requires authoritative CI, device runtime verification, evidence
  closure, local/origin/VPS exact match, and immutable previous tags.
