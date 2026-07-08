# Sprint 25 — Tenant Lifecycle Enforcement & Manual Suspension Governance Foundation

## Scope

Establish a server-side authoritative tenant lifecycle source of truth and
platform-admin manual suspension governance, with real runtime enforcement that
blocks suspended tenants from operational (POS) APIs. Manual suspension has
precedence over Sprint 24 subscription renewal/dunning automation. Foundation
rules `TLS-R001..R010` are locked in config, docs, tests, and a CI gate.

This is a **runtime enforcement** sprint, not docs-only.

## Runtime changes

- New middleware `EnsureTenantLifecycleAllowed` (alias `tenant.lifecycle`) added
  to the operational tenant route group in `routes/api.php` after
  `subscription.active`. A blocked tenant returns `423 Locked` with
  `{ message, code: TENANT_SUSPENDED, tenant_status }`.
- Lifecycle status computed only by `TenantLifecycleService` (source of truth),
  surfaced via `TenantLifecycleAccessGuard`.
- Enforcement allowlist (auth/status/device/tenant-context/health/webhook/admin)
  stays reachable while suspended.

## Migrations / schema

- `tenant_manual_suspensions` — manual suspension records (ACTIVE/LIFTED).
- `tenant_lifecycle_events` — append-only lifecycle event trail.
- `Tenant` gains `manualSuspensions()`, `lifecycleEvents()`,
  `activeManualSuspension()`.

## Services (`App\Services\TenantLifecycle`)

- `TenantLifecycleStatus` — status vocabulary + blocked set.
- `TenantLifecycleDecision` — immutable decision DTO.
- `TenantLifecycleService` — source of truth (precedence: manual → tenant status
  → subscription).
- `TenantLifecycleAccessGuard` — shared allow/block decision point.
- `TenantSuspensionService` — idempotent, audit-logged suspend/lift (only writer
  of `tenant_manual_suspensions`).
- `SanitizesTenantLifecycleText` — reason/metadata sanitizer.
- `TenantSuspensionSummaryService` — secret-safe counts.
- `TenantLifecycleEnforcementAuditService` — guard coverage + config audit.
- `TenantLifecycleReadinessService` / `TenantLifecycleGoNoGoService`.

## Routes / controllers (platform admin only)

- `GET  /api/v1/admin/tenants/{tenant}/lifecycle`
- `POST /api/v1/admin/tenants/{tenant}/suspend`
- `POST /api/v1/admin/tenants/{tenant}/lift-suspension`
- `GET  /api/v1/admin/tenant-lifecycle/suspension-summary`

Controllers: `AdminTenantLifecycleController`, `AdminTenantSuspensionController`,
`AdminTenantLifecycleSuspensionSummaryController`. Requests: `SuspendTenantRequest`,
`LiftTenantSuspensionRequest`. Resources: `TenantLifecycleResource`,
`TenantManualSuspensionResource`, `TenantSuspensionSummaryResource`.

## Commands

- `tenant-lifecycle:readiness`
- `tenant-lifecycle:suspension-summary`
- `tenant-lifecycle:enforcement-audit`
- `tenant-lifecycle:go-no-go`

All support `--json`; readiness/enforcement-audit/go-no-go support `--strict` and
exit non-zero on NO_GO.

## Enforcement allowlist

See [../tenant-lifecycle/enforcement-allowlist.md](../tenant-lifecycle/enforcement-allowlist.md).

## Manual suspension governance

See [../tenant-lifecycle/manual-suspension-governance.md](../tenant-lifecycle/manual-suspension-governance.md).
Platform-admin only, idempotent, mandatory sanitized reason, audit-logged.

## Subscription renewal / dunning interaction

See [../tenant-lifecycle/renewal-dunning-precedence.md](../tenant-lifecycle/renewal-dunning-precedence.md).
Manual suspension has precedence; renewal/dunning can never auto-suspend or
auto-reactivate.

## Rules aplikasi added

`TLS-R001..R010` in `config/tenant_lifecycle.php` and
`docs/architecture/tenant-lifecycle-governance.md`; runtime rule section in
`docs/PROJECT_RULES.md`; sprint flags in `config/pos_foundation.php`. Locked by
`TenantLifecycleRulesLockTest` and the `tenant-lifecycle` gate.

## Test / gate evidence

- `Tests\Feature\TenantLifecycle\*` — suspension governance, enforcement,
  precedence, commands.
- `Tests\Unit\TenantLifecycle\*` — service precedence, rules lock.
- Commands run green: readiness / suspension-summary / enforcement-audit /
  go-no-go.

## CI evidence

`.github/workflows/sprint25-ci.yml` — foundation+smoke, backend tests, prior
Sprint 13–24 gates, subscription renewal gate, tenant lifecycle gate
(`tenant-lifecycle:go-no-go`), Android build+unit tests. `scripts/sprint25_smoke.sh`.

## PR / merge / tag evidence

- PR: Sprint 25 — Tenant Lifecycle Enforcement & Manual Suspension Governance Foundation.
- GO tag: `sprint-25-tenant-lifecycle-enforcement-manual-suspension-governance-foundation-go`.

## Risks / allowlist

- The lifecycle guard intentionally covers only the operational group; the
  documented allowlist (auth/status/device/webhook/admin) stays open by design so
  suspended tenants can still authenticate and see status. `enforcement-audit`
  fails if any operational route loses the guard.
