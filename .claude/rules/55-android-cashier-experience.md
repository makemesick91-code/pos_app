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

## Build-variant endpoint & pilot connectivity (physical-device fix)
- UIX7-R045 — Emulator development and physical-device pilot API endpoints must
  use explicit separate build variants (`debug` vs `pilot`); they are never the
  same artifact reconfigured at runtime.
- UIX7-R046 — Debug emulator builds may use the `10.0.2.2` host alias, but pilot
  and release builds must resolve to the governed HTTPS backend
  (`https://aishpos.online/`). A physical-device build must never ship the
  emulator alias.
- UIX7-R047 — Pilot and release variants must deny cleartext traffic and must
  never use a trust-all TrustManager or a disabled/overridden hostname
  verification; HTTP logging must not run for the debuggable pilot variant.
- UIX7-R048 — Localhost and emulator cleartext exceptions must remain in the
  debug-only Android source set (`src/debug/res/xml`) and must not enter the
  pilot or release merged manifest/network-security config.
- UIX7-R049 — A physical-device pilot APK must be installable, signed by an
  approved pilot/debug certificate, source-traceable, and verified to contain the
  governed pilot HTTPS API URL.
- UIX7-R050 — Connection-error investigation must distinguish DNS, TLS,
  transport, authentication, authorization, and invalid-build-endpoint failures
  using observed evidence, never assumption.
- UIX7-R051 — UIX-7 GO remains blocked until physical-device authenticated
  verification, offline/reconnect verification, synthetic cleanup, and evidence
  closure are complete; on-device evidence is operator-captured and never
  fabricated.

## Physical-device runtime closure & GO discipline (runtime-closure)
- UIX7-R052 — Device activation must bind the authenticated Cashier, tenant,
  outlet, device identifier, and activation state without granting cross-tenant
  or elevated (Platform Admin / Tenant Owner) access.
- UIX7-R053 — A transaction may be presented as successful only after the
  required durable local save or canonical server acknowledgement defined by the
  transaction mode (online = server ack; offline = durable local save).
- UIX7-R054 — Every transaction attempt must use a stable idempotency key
  (`clientReference`) preserved across retries, process restart, and reconnect.
- UIX7-R055 — Rapid tap, retry, reconnect, and worker replay must produce
  exactly one canonical financial transaction.
- UIX7-R056 — Offline transactions must survive force-stop, process death,
  application restart, device restart where supported, and temporary loss of
  connectivity.
- UIX7-R057 — A local transaction may transition to synced only after canonical
  server acknowledgement is durably recorded.
- UIX7-R058 — Cart total, transaction total, payment total, change, receipt
  total, history total, and backend total must match exactly using integer
  monetary units (whole rupiah).
- UIX7-R059 — A stale previous receipt or transaction result must never be
  displayed as the result of the current cart.
- UIX7-R060 — QRIS created or awaiting payment must never be presented as paid,
  confirmed, settled, or successful.
- UIX7-R061 — QRIS status transitions must be monotonic, auditable, idempotent,
  tenant-scoped, and correlated to exactly one transaction.
- UIX7-R062 — Runtime evidence source is governed by scenario classification
  (`docs/governance/android-runtime-evidence-governance.md`). Hardware-dependent
  and OEM-specific scenarios MUST be captured on an actual physical device.
  Hardware-independent scenarios MAY use controlled Android **emulator** evidence
  as authoritative, provided it is source-attributed, commit-bound, and auditable.
  Emulator evidence MUST NEVER be labelled or represented as physical-device
  evidence, and unit-test evidence is never a substitute for either.
- UIX7-R063 — Runtime logs and screenshots must redact credentials, tokens,
  customer PII, payment secrets, and QR payloads.
- UIX7-R064 — Accessibility verification must include TalkBack, focus order,
  semantic labels, touch targets, font scaling, error announcements, and the
  primary cashier workflows.
- UIX7-R065 — All synthetic Cashier, device, product, transaction, payment,
  QRIS, sync queue, and test artifacts must be removed or deactivated before
  UIX-7 GO.
- UIX7-R066 — UIX-7 GO requires local, origin, VPS, final evidence commit, and
  annotated tag peeled commit to exact-match.
- UIX7-R067 — Any runtime-discovered source defect requires regression tests and
  one authoritative full CI on the final candidate.
- UIX7-R068 — Evidence-only closure may use lightweight CI only when the
  CICD-CTRL-2 classifier proves that no executable, source, workflow, rules,
  dependency, schema, config, or test file changed.
- UIX7-R069 — A runtime defect involving financial correctness, transaction
  loss, duplication, authorization, tenant isolation, QRIS false-success, or
  credential leakage is an automatic NO-GO.
- UIX7-R070 — Runtime verification (physical for hardware-dependent rows,
  authoritative emulator or physical for hardware-independent rows), cleanup,
  evidence, VPS synchronization, DMS non-regression, and tag exact-match are all
  mandatory for GO.

## Runtime evidence source governance (emulator-evidence unblock, UIX7-R071..R080)

Formalises `docs/governance/android-runtime-evidence-governance.md` (policy
v1.0.0). Extends, and does not weaken, UIX7-R052..R070.

- UIX7-R071 — Every runtime scenario MUST carry exactly one hardware
  `classification` (`hardware_independent` / `hardware_neutral` /
  `hardware_dependent` / `oem_specific`) and every evidence row MUST name its
  `evidence_source` (physical / emulator / automated_test / database / ci / vps).
- UIX7-R072 — Controlled emulator evidence MAY be authoritative for
  `hardware_independent` scenarios (offline durable save, process-kill
  restoration, reconnect, idempotent sync, receipt/history parity, accessibility
  semantics, crash/ANR/log/cleartext inspection).
- UIX7-R073 — Physical-device evidence REMAINS REQUIRED for `hardware_dependent`
  and `oem_specific` scenarios (camera/scanner, Bluetooth/USB printer, NFC,
  biometric, hardware keystore, physical payment peripheral, OEM background
  restrictions). Emulator evidence for such a row is a BLOCKING gate failure.
- UIX7-R074 — Mixed evidence (physical for some rows, emulator for others) is
  permitted; each row is judged against its own classification.
- UIX7-R075 — Emulator evidence MUST NEVER be relabelled, represented, or
  aggregated as physical-device evidence; the source field is immutable and
  auditable.
- UIX7-R076 — Every authoritative runtime row MUST be bound to the exact
  candidate commit SHA, app version, APK SHA-256, build variant, environment,
  timestamp, and verification method; a missing/empty binding is a BLOCKING gate
  failure and stale (commit-mismatched) evidence is rejected.
- UIX7-R077 — Authoritative runtime evidence MUST use a release-equivalent APK on
  an app-supported emulator API; debug-only behavior is never the sole GO proof.
- UIX7-R078 — The closure gate MUST validate a structured, machine-parseable
  evidence manifest (not a bare `PASS` string search), MUST fail closed on any
  incomplete/ambiguous row, and MUST have regression tests.
- UIX7-R079 — This policy is NOT retroactive: it never converts previously-absent
  evidence into a PASS, and a `FAIL`/`BLOCKED`/`PENDING` row stays so until
  genuine classified evidence is captured.
- UIX7-R080 — This runtime-evidence foundation is the mandatory baseline for
  every subsequent Android sprint, including UIX-8.
