# CLAUDE.md — Aish POS Project Instructions

Aish POS is a multi-tenant Android Point-of-Sale SaaS. The Laravel backend lives in
`backend/`. This file is the top-level index; detailed, enforceable rules live in
`.claude/rules/` and `docs/PROJECT_RULES.md`. When a rule here and a modular rule
appear to conflict, the modular rule in `.claude/rules/` is authoritative.

## Product identity
- Aish POS = white/blue branded, tenant-scoped POS for small merchants, sold as SaaS.
- Backend: Laravel (PHP 8.5) in `backend/`. Build-free Blade for web surfaces (no Node/Vite at deploy).
- Do not add tenant-facing admin web consoles beyond what a sprint authorizes.

## Five-surface architecture
1. Public website — Blade marketing/landing pages, no privileged data.
2. Android / tenant API — Sanctum tokens, tenant-scoped; every business route runs the
   lifecycle → entitlement → usage-limit guard chain.
3. Platform Admin API — `/api/v1/admin/*`, Sanctum + `platform.admin` middleware.
4. Platform Admin browser console — `/admin/*`, session/cookie + `platform.admin.web`
   middleware (delivered by UIX-3).
5. Tenant Owner browser console — `/owner/*`, session/cookie on the dedicated `owner`
   guard + `tenant.owner.web` middleware (delivered by UIX-4). Read-only, tenant-scoped
   to the authenticated owner's own tenant; never a platform capability. See
   `.claude/rules/25-tenant-owner-web-console-boundary.md`.

## Multi-tenant boundary
- Tenant data is always scoped to the authenticated tenant; never read/write across tenants.
- `TenantLifecycleService::resolve()` is the ONLY authoritative tenant status. Do not
  recompute suspension/trial/paid state in controllers or Blade.

## Platform-admin boundary
- Platform admin identity = `users.is_platform_admin` (boolean) AND `is_active`.
  Never a tenant role, never a tenant permission.
- The predicate lives in `App\Http\Middleware\EnsurePlatformAdmin` (API) and
  `EnsurePlatformAdminWeb` (web). Do not reimplement the check inline.

## Source-of-truth rule
- Business truth lives in `App\Services\*` domain services. Billing, entitlement, usage,
  QRIS/payment, onboarding, lifecycle each have a canonical service — reuse it.
- Controllers and Blade views orchestrate and present only; they must not duplicate or
  fork business logic that a service already owns.

## Security baseline
- No production default credentials. No seeded/hardcoded admin passwords in any
  environment that can be reached in production. Secrets come from environment only.
- Never expose secrets, tokens, or raw payment data in responses, logs, or Blade output.
- Redact through `App\Services\Admin\AdminAuditLogger::sanitize()` before logging.

## Testing command index
- Full suite: `php artisan test` (in-memory sqlite), run from `backend/`.
- Targeted: `php artisan test --filter <TestName>`.
- Platform admin tests build users via `User::factory()->platformAdmin()`.
- Design gates: `scripts/uix1_design_gate.sh`, `scripts/uix2_design_gate.sh`,
  `scripts/uix3_design_gate.sh`.
- Foundation rules check: `scripts/verify_application_foundation_rules.sh`.

## Authoritative CI
- The `pull_request` workflows are the authoritative gate. Local runs are advisory.
- A change is not "green" until the PR workflows pass; do not merge on local pass alone.

## Deployment isolation
- Shared VPS, co-tenanted with DaengtisiaMS (asia-dental-lab-v2). Aish is fully isolated:
  php8.5-fpm pool `aish-pos`, nginx site `aish-pos` on port 8080, systemd unit
  `aish-pos-queue-worker`, database `aish_pos_pilot`.
- DaengtisiaMS uses php8.3-fpm and must NEVER be modified. Aish work must never touch
  php8.3, the `daeng` user, or DMS nginx/systemd/DB.
- No HTTPS/domain yet: public plaintext admin exposure is NO-GO. The admin console is
  reachable only over an encrypted operator channel (SSH tunnel / VPN).

## DMS non-regression
- Every deploy must be preceded and followed by a DaengtisiaMS non-regression check.
  If DMS is affected in any way, the change is NO-GO and must be rolled back.

## GO-tag rule
- No GO tag before: successful deploy + runtime verification + DMS non-regression +
  real (non-placeholder) evidence captured.
- Existing GO tags are immutable. The final release commit must be equal across local,
  origin, and the VPS. GO tags are annotated.

## Tenant-owner boundary (UIX-4)
- Tenant owner identity = `users.is_active` AND `role = tenant_owner` AND a resolvable
  `tenant_id`. Never a platform capability, never grantable through platform flows.
- The `/owner/*` console runs on the `owner` session guard, distinct from the
  platform-admin `web` guard; neither session satisfies the other's gate.
- Tenant context is derived server-side from the owner's own record only — never from a
  route parameter, query string, header, cookie, or hidden field. The domain is one
  tenant per user; there is no tenant switcher. See UIX4-R001..R022.

## Billing console boundary (UIX-5)
- The Subscription/Billing/Invoice console is read-only presentation over the
  canonical billing domain on two surfaces: Tenant Owner Billing Center
  (`/owner/billing/*`, `owner` guard) and Platform Admin Billing Operations
  (`/admin/billing/*`, `platform.admin.web`). It is not a second billing engine.
- Money is whole-rupiah integer only — never a float, never `/100` cents. Totals,
  paid, and outstanding come from canonical services/model methods
  (`BillingConsoleReadService`, `BillingSummaryService`, invoice model), never
  recomputed in controllers/Blade. Format money only through `<x-rupiah>`; a null
  value renders "Tidak tersedia", never a fabricated zero.
- Owner billing is tenant-scoped, deny-by-default; a foreign/unknown invoice id is
  404 and owner invoices never use implicit route-model binding. Invoice documents
  are authenticated, non-path-traversable, with no public URL. The console is
  read-only (no billing mutation route). QRIS/settlement state stays distinct from
  invoice collection state ("Lunas" only when `collection_state = paid`). See
  `.claude/rules/35-subscription-billing-invoice-integrity.md` (UIX5-R001..R028).

## Support/observability/incident console boundary (UIX-6)
- The Support, Observability & Incident console is read-only presentation over the
  canonical Sprint 35 (`App\Services\SupportOperations\*`) and Sprint 36
  (`App\Services\Observability\*`) domains on two surfaces: Platform Admin
  (`/admin/support/*`, `/admin/observability`, `/admin/incidents/*`,
  `platform.admin.web`) and Tenant Owner (`/owner/support/*`, `owner` guard). It
  is not a second support/alert/incident/health engine.
- Read adapters live in `App\Services\SupportConsole\*` and never recompute health,
  alert, or incident state. Owner access is tenant-scoped and deny-by-default; a
  foreign/unknown incident id is 404 and owner incidents never use implicit
  route-model binding. The console is read-only (no mutation route); incident
  transitions stay in their governed services.
- Truthful health: unknown/stale health is never presented as healthy — the
  observability presenter marks a component `unknown` when evidence is absent/stale
  and surfaces snapshot freshness. Unsupported values render "Tidak tersedia". No
  raw logs, stack traces, secrets, or infrastructure identifiers reach the browser.
  See `.claude/rules/45-support-observability-incident-governance.md` (UIX6-R001..R033).

## Android cashier experience boundary (UIX-7)
- The Android Cashier app (`com.aishtech.poslite`, native Views/XML) is a distinct
  tenant/device-scoped POS surface. It authenticates with Sanctum API tokens and
  never inherits Platform Admin (`/admin/*`) or Tenant Owner (`/owner/*`) web
  authorization. Business truth stays in the backend `App\Services\*` domains and
  the app's canonical repositories/managers; UI/ViewModels present and orchestrate
  only — no duplicated pricing, tax, payment, QRIS, settlement, or sync logic.
- Offline durability before success: an offline sale is cleared from the cart only
  after a durable local save, an interrupted in-flight sync is recoverable (never
  stranded/lost), retries are idempotent on the device `clientReference` (no
  duplicate server transactions), and SYNCED is shown only on server ack.
- Money is canonical whole-rupiah integer (`core/money/RupiahMoney`, `Long`) —
  never unsafe float in new/changed cashier code — and formatted only through the
  single canonical formatter; unknown renders "Tidak tersedia". QRIS created ≠
  paid/settled; QRIS is online-only. Checkout has a ViewModel-level double-submit
  guard. Tokens/tenant DB are not backed up (`allowBackup=false`); cleartext HTTP
  is denied by default (release/pilot → `https://aishpos.online`). No credentials/
  tokens/PII in logs, screenshots, or test artifacts.
- Endpoints are build-variant-scoped: only the emulator `debug` build uses
  `http://10.0.2.2:8000/` (with debug-only cleartext exceptions in
  `src/debug/res/xml`); the installable, debug-signed `pilot` variant and
  `release` target `https://aishpos.online/` over TLS-only, no trust-all, and no
  HTTP logging. Physical-device pilot verification uses `assemblePilot`
  (UIX7-R045..R051).
- This environment cannot build/run Android (no SDK, JDK 25); CI (JDK 21) is the
  build/test gate. On-device authenticated runtime verification against
  `aishpos.online` and the GO tag are operator-performed and deferred until real
  device evidence is captured — never fabricated. See
  `.claude/rules/55-android-cashier-experience.md` (UIX7-R001..R044).

## Android cashier premium visual & transaction foundation (UIX-8)
- UIX-8 is a premium visual + transaction-experience remediation of the native
  Android Cashier (`com.aishtech.poslite`). It extends — never weakens — rule 55.
  It stays native (no WebView cashier), keeps Room/WorkManager/device activation,
  and never becomes a second pricing/payment/QRIS/settlement/sync engine.
- The on-device design system (Material 3 semantic tokens in
  `res/values/colors.xml|dimens.xml|styles.xml|themes.xml`, zero hardcoded hex in
  layouts, `Widget.Aish.*`/`TextAppearance.Aish.*`) is the single visual source of
  truth; the brand gradient is a sparing accent only. Authoritative money is
  whole-rupiah integer (`RupiahMoney`, `Long`): the cart/checkout arithmetic is
  integer-exact and projects to legacy Double only at one storage/DTO boundary;
  tendered cash is parsed via `RupiahMoney.parse` (never a fabricated 0). Offline
  sync retries are bounded (`OfflineSaleRepository.MAX_SYNC_ATTEMPTS`) so a poison
  row cannot starve the queue yet stays FAILED/visible. Stable `clientReference`,
  ViewModel double-submit guard, durable-save-before-cart-clear, and orphan-SYNCING
  recovery are preserved.
- UIX-7 closure debt stays explicit: UIX-8 development is unblocked but MUST NOT
  create a UIX-7 GO tag, alter historical UIX-7 evidence to PASS, or claim UIX-7
  runtime closure is complete. UIX-8 GO requires UIX-7 closure OR a formal,
  auditable, time-bounded risk acceptance (never declaring UIX-7 PASS); otherwise
  the honest state is `IMPLEMENTATION COMPLETE — GO DEFERRED`. On-device/emulator
  authenticated runtime verification and the annotated GO tag are operator-
  performed and never fabricated. Closure gate:
  `scripts/uix8_runtime_closure_gate.sh` (fail-closed). See
  `.claude/rules/56-android-cashier-premium-visual-transaction-foundation.md`
  (UIX8-R001..R048).

## Android cashier native premium screen rebuild (UIX-8B)
- UIX-8B completes the native premium cashier **screen** rebuild on top of the
  UIX-8A foundation. It rebuilds the actual surfaces — cashier home, product
  experience, cart, native cash payment, success/receipt, transaction history —
  and all UI states (loading/empty/offline/error/session/device) to a premium,
  accessible, truthful standard. It stays native (no WebView cashier), reuses the
  UIX-8A design system and integer-exact `RupiahMoney`, and never becomes a
  second pricing/payment/QRIS/settlement/sync engine. It extends — never weakens
  — rules 55 and 56.
- Screens have a single authoritative ViewModel state holder; navigation never
  duplicates checkout or shows stale receipts; the receipt binds to the current
  transaction; cash tender is parsed via `RupiahMoney.parse`; checkout keeps the
  ViewModel double-submit guard and stable `clientReference`; QRIS stays hidden
  until a complete backend lifecycle exists. Accessibility (labels, focus order,
  touch targets, font scaling, never-colour-alone) is a release gate.
- UIX-7 closure debt stays explicit: UIX-8B development is unblocked but MUST NOT
  create a UIX-7 GO tag, flip historical UIX-7 evidence to PASS, or claim UIX-7
  closure. UIX-8 GO requires UIX-7 closure OR a formal auditable time-bounded
  waiver; otherwise the honest state is `IMPLEMENTATION COMPLETE — GO DEFERRED`.
  On-device/emulator authenticated runtime verification and the annotated GO tag
  are operator-performed and never fabricated. See
  `.claude/rules/57-android-cashier-premium-screen-rebuild.md`
  (UIX8B-R001..R100).

## Android Bluetooth permission foundation (FIX-BT-SCAN)
- Every Android Bluetooth integration (starting with the ESC/POS printer
  transport `BluetoothPrinterConnection`) obeys a least-privilege permission
  contract: protected calls have a single explicit permission owner, runtime
  checks are API-level correct (`BLUETOOTH_CONNECT` runtime on API 31+, legacy
  `BLUETOOTH`/`BLUETOOTH_ADMIN` capped at API 30), and `BLUETOOTH_SCAN` is never
  declared or called because the transport does no discovery. Denied permission
  returns an actionable typed failure and never crashes or reaches a protected
  API; `SecurityException` is handled defensively, never hidden. Blanket
  `MissingPermission` suppression is forbidden; location permission is never
  added to silence Bluetooth lint. Printer failures never affect transaction
  persistence or authority. This fix never fabricates a UIX-7/UIX-8 GO or alters
  their closure debt. See
  `.claude/rules/58-android-bluetooth-permission-foundation.md`
  (BTPERM-R001..R029).

## Authoritative CI consolidation (CICD-CTRL-2)
- CI is consolidated into four lanes driven by a fail-closed change classifier
  (`scripts/ci/classify_changes.sh`). The single authoritative gate is **AISH POS
  Authoritative PR CI** (`ci-authoritative.yml`, `on: pull_request`): exactly one
  complete validation per final source candidate, concurrency-cancelling stale
  SHAs, ending in a truthful `authoritative-summary` gate. `main` runs
  `ci-main-smoke.yml` (source-equivalence + deployability, escalating to full when
  unproven). Docs/evidence changes take a lightweight lane ONLY when the classifier
  confirms the strict allowlist — rules, workflows, scripts, deps, schema, config,
  tests, and source never qualify and always force full CI.
- Shared logic lives in reusable workflows (`_backend-tests`, `_android-build`,
  `_foundation-gates`, `_security-validation`, `_evidence-validation`). The 45
  legacy `sprint*-ci`/`uix*-ci` workflows are neutralized to `workflow_dispatch`
  (kept, manual-only), never deleted. Optimization removes redundant execution and
  never weakens a required gate. `main` is not branch-protected, so the authoritative
  gate is enforced by rule + reviewer discipline. See
  `.claude/rules/72-authoritative-ci-consolidation.md` (CICD2-R001..R024).

## Pointers
- Modular enforceable rules: `.claude/rules/` (00–90, plus 25 tenant-owner boundary,
  35 billing, 45 support/observability, 55 android cashier, 56 android cashier
  premium visual & transaction foundation, 57 android cashier native premium
  screen rebuild, 58 android bluetooth permission foundation, 72 authoritative CI
  consolidation). Legacy line kept for continuity:
  35 billing-console integrity, 45 support/observability/incident governance, and
  55 android cashier experience). Root agent index: `AGENTS.md`.
- Full project rules & rule-set IDs (incl. UIX3-R001..R016): `docs/PROJECT_RULES.md`.
- Architecture/foundation docs: `docs/foundation/` (see
  `docs/foundation/uix-3-platform-admin-control-center.md`).
- Governance synthesis: `docs/governance/application-foundation-rules.md`.
- Deployment runbook: see `docs/` deployment runbook and pilot deployment docs.
