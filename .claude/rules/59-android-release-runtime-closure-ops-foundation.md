# 59 — Android Release, Deployment & Runtime-Closure Ops Foundation (UIX-8B-OPS-1)

Permanent, enforceable foundation for the **operational closure** of every
Android cashier release: shared-VPS deployment, DaengtisiaMS co-tenant
non-regression, controlled runtime-evidence capture, transaction/idempotency
proof, accessibility observation, evidence-closure binding, and release-debt
discipline. Introduced by UIX-8B-OPS-1 while driving the UIX-8 GO closure on top
of UIX-8A (rule 56) and UIX-8B (rule 57).

This rule **extends and never weakens** rules 55 (UIX7-R001..R080), 56
(UIX8-R001..R048), 57 (UIX8B-R001..R100), 58 (BTPERM-R001..R029), 70/72 (CI),
80 (deployment/DMS), and 90 (release/GO). Business truth and transaction
authority stay in the backend `App\Services\*` domains and the app's canonical
repositories/managers; the release process observes and attests only — it never
invents a passing result.

## Deployment foundation
- **UIX8BOPS-R001** — VPS deploy MUST use the official traceable Git-based
  mechanism; the deployed commit MUST be identifiable.
- **UIX8BOPS-R002** — Manual source-file copy MUST NOT replace Git-based
  deployment.
- **UIX8BOPS-R003** — Local, origin, and VPS MUST exact-match before any GO tag.
- **UIX8BOPS-R004** — The VPS worktree MUST be clean before and after deploy.
- **UIX8BOPS-R005** — Runtime file ownership MUST remain `www-data:www-data`.
- **UIX8BOPS-R006** — Final cache/artisan operations MUST run as (or be restored
  to) the correct PHP-FPM runtime user.
- **UIX8BOPS-R007** — A deploy MUST NOT leave root-owned runtime files under
  `storage/framework` or `bootstrap/cache`.
- **UIX8BOPS-R008** — Migrations MUST run only when migrations exist and are
  pending.
- **UIX8BOPS-R009** — Composer install MUST run only when dependency state
  requires it.
- **UIX8BOPS-R010** — Backend cache rebuild MUST NOT be performed unnecessarily.
- **UIX8BOPS-R011** — HTTPS root, `/live`, `/ready`, and health MUST be verified
  after deploy.
- **UIX8BOPS-R012** — Service state MUST be captured before and after deploy.
- **UIX8BOPS-R013** — A rollback reference (previous release commit + backup)
  MUST be recorded before deploy.

## Co-tenant DaengtisiaMS foundation
- **UIX8BOPS-R014** — DaengtisiaMS MUST receive a before-deploy snapshot.
- **UIX8BOPS-R015** — DaengtisiaMS MUST receive an after-deploy snapshot.
- **UIX8BOPS-R016** — DMS HEAD MUST NOT change during an Aish POS deploy.
- **UIX8BOPS-R017** — The DMS worktree MUST remain clean.
- **UIX8BOPS-R018** — The DMS database MUST NOT be migrated by Aish POS scripts.
- **UIX8BOPS-R019** — DMS config MUST NOT be modified.
- **UIX8BOPS-R020** — DMS services (php8.3-fpm, nginx, PostgreSQL, queue) MUST
  remain active.
- **UIX8BOPS-R021** — A pre-existing unrelated failure MUST be distinguished from
  a regression with evidence that it predates the deploy.
- **UIX8BOPS-R022** — Aish POS GO MUST fail if any DMS regression is detected.

## Runtime evidence foundation
- **UIX8BOPS-R023** — Runtime evidence MUST bind to the exact candidate commit.
- **UIX8BOPS-R024** — Runtime evidence MUST bind to the APK SHA-256.
- **UIX8BOPS-R025** — Every operator run MUST carry a unique run ID.
- **UIX8BOPS-R026** — Dependent transaction scenarios MUST share one run ID.
- **UIX8BOPS-R027** — Dependent transaction scenarios MUST share one
  `clientReference`.
- **UIX8BOPS-R028** — The offline durable-save row MUST PASS before the
  restoration/sync/idempotency rows that depend on it may PASS.
- **UIX8BOPS-R029** — Screenshot existence alone MUST NOT imply PASS.
- **UIX8BOPS-R030** — Operator PASS MUST be explicit.
- **UIX8BOPS-R031** — The recorded observation MUST be substantive.
- **UIX8BOPS-R032** — An empty or generic observation MUST remain PENDING.
- **UIX8BOPS-R033** — A missing screenshot MUST remain PENDING.
- **UIX8BOPS-R034** — A missing transaction reference (where required) MUST remain
  PENDING.
- **UIX8BOPS-R035** — Missing DB proof MUST block an idempotency PASS.
- **UIX8BOPS-R036** — Emulator evidence MUST remain labelled emulator.
- **UIX8BOPS-R037** — Automated/unit evidence MUST remain labelled automated.
- **UIX8BOPS-R038** — Operator-observed evidence MUST remain labelled
  operator-observed.
- **UIX8BOPS-R039** — Evidence from different APKs MUST NOT be combined without
  explicit revalidation.

## Transaction evidence foundation
- **UIX8BOPS-R040** — An offline transaction MUST be durable before cart clear.
- **UIX8BOPS-R041** — Force-stop MUST be a genuine process kill.
- **UIX8BOPS-R042** — Restoration MUST show the same pending transaction.
- **UIX8BOPS-R043** — Reconnect MUST use the official WorkManager/app sync path.
- **UIX8BOPS-R044** — A retry MUST reuse the same stable `clientReference`.
- **UIX8BOPS-R045** — Exactly one sale MUST be proven.
- **UIX8BOPS-R046** — Exactly one payment MUST be proven.
- **UIX8BOPS-R047** — Sale-item count MUST match the known cart.
- **UIX8BOPS-R048** — The receipt MUST match the backend-persisted transaction.
- **UIX8BOPS-R049** — History MUST show exactly one transaction.
- **UIX8BOPS-R050** — Receipt/history money MUST remain integer-exact
  (whole-rupiah `Long`).

## Accessibility foundation
- **UIX8BOPS-R051** — A TalkBack PASS MUST require actual TalkBack use.
- **UIX8BOPS-R052** — A UI-tree dump alone MUST NOT prove focus order.
- **UIX8BOPS-R053** — Focus order MUST be manually observed.
- **UIX8BOPS-R054** — Spoken labels MUST be meaningful.
- **UIX8BOPS-R055** — Status MUST NOT rely on colour alone.
- **UIX8BOPS-R056** — A large-font PASS MUST require visual observation.
- **UIX8BOPS-R057** — Primary actions MUST remain visible at large font.
- **UIX8BOPS-R058** — Receipt/history MUST remain usable at large font.

## Evidence-closure foundation
- **UIX8BOPS-R059** — The runtime candidate and the evidence-closure commit MAY
  differ only through evidence-only changes.
- **UIX8BOPS-R060** — The candidate MUST be an ancestor of the evidence-closure
  commit.
- **UIX8BOPS-R061** — The evidence-only diff MUST NOT modify runtime code.
- **UIX8BOPS-R062** — The evidence-only diff MUST NOT modify dependencies.
- **UIX8BOPS-R063** — The evidence-only diff MUST NOT modify schema or API
  contracts.
- **UIX8BOPS-R064** — Runtime evidence MUST be rerun after any runtime-affecting
  change.
- **UIX8BOPS-R065** — The final evidence manifest MUST record candidate,
  evidence-closure, and tagged commit.
- **UIX8BOPS-R066** — The closure gate MUST fail closed.
- **UIX8BOPS-R067** — The GO tag MUST NOT be created before closure PASS.
- **UIX8BOPS-R068** — The GO tag MUST be annotated.
- **UIX8BOPS-R069** — Prior GO tags MUST remain immutable.
- **UIX8BOPS-R070** — Absence of proof MUST remain NO-GO.

## Release-debt foundation
- **UIX8BOPS-R071** — UIX-7 debt MUST NOT be silently converted to PASS.
- **UIX8BOPS-R072** — UIX-7 debt closure MUST use genuine evidence.
- **UIX8BOPS-R073** — A waiver MUST require genuine owner approval.
- **UIX8BOPS-R074** — A waiver MUST be time-bounded.
- **UIX8BOPS-R075** — A waiver MUST list unresolved scenarios and mitigations.
- **UIX8BOPS-R076** — A waiver MUST NOT retroactively declare UIX-7 PASS.
- **UIX8BOPS-R077** — UIX-8 GO MUST fail if neither UIX-7 closure nor a valid
  waiver exists.
- **UIX8BOPS-R078** — All OPS foundation rules above become mandatory for every
  future Android release closure.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists
  and that its rule IDs are persisted.
- `scripts/uix8_runtime_closure_gate.sh` enforces the fail-closed runtime/GO
  discipline (candidate binding, zero non-PASS rows, UIX-7 debt closed-or-waived,
  CI/PR/exact-match, DMS OK, target tag absent).
- The turnkey operator evidence runner `scripts/uix8_operator_runner.sh` captures
  genuine operator-observed evidence fail-closed (blank/generic observation stays
  PENDING; dependency chain enforced; no auto-PASS).
- Because `main` is not branch-protected, GO discipline is enforced by rule and
  reviewer discipline; do not tag until every gate is genuinely met. Operator
  observation is a human checkpoint and is never fabricated.
