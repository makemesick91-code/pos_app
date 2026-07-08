# Sprint 35 — Support Console, Tenant Operations & Incident Diagnostics — Evidence

Base: main @ Sprint 34 merge `0358d5b3b857a99c98e0ea2c7864964823e2e268`
(GO tag `sprint-34-android-offline-sync-device-activation-cashier-runtime-hardening-go`).

Branch: `feature/sprint-35-support-console-tenant-operations-incident-diagnostics`.

## Runtime shipped

Platform-admin support console (`api/v1/admin/support-ops/*`) that makes the SaaS
operationally supportable without direct DB access: tenant health, diagnostic
timeline, read-only billing/payment/entitlement/onboarding/device/sync viewers,
blocked/denied action explorer, sync failure inspection, governed device
revoke/reactivate, tenant-isolated incident notes, support audit trail, time-bound
read-only support context, and impersonation disabled-by-default. Additive; no
prior-sprint behaviour changed.

## Governance (SUP-R001..R030)

Declared in `backend/config/support_operations_governance.php` and mirrored into
`backend/config/pos_foundation.php` (`support_operations_rules_sprint_35`) and
`docs/PROJECT_RULES.md`. Ten hard guardrails are locked `false`:

- `support_marks_invoice_paid_allowed`
- `support_unlocks_entitlement_allowed`
- `support_bypasses_payment_settlement_allowed`
- `support_lifts_manual_suspension_allowed`
- `support_reactivates_suspended_tenant_allowed`
- `support_mutates_state_without_governed_service_allowed`
- `support_console_public_or_tenant_mutation_allowed`
- `impersonation_enabled_without_governance_allowed`
- `impersonation_exposes_raw_credentials_allowed`
- `support_output_leaks_secret_or_pii_allowed`

`support-ops:governance-audit` → all PASS. `support-ops:go-no-go --strict` → GO
once the Sprint 35 docs are present (below). The hard gate `SUP-R030` requires
go/no-go to verify tenant health, timeline, incident notes, support audit, device
support flow, sync diagnostics, billing/payment/entitlement/onboarding visibility
and redaction — all asserted here.

## Tables / models added

- `tenant_support_incidents` → `App\Models\TenantSupportIncident`
- `tenant_support_incident_notes` → `App\Models\TenantSupportIncidentNote`
- `tenant_support_actions` → `App\Models\TenantSupportAction`
- `tenant_support_sessions` → `App\Models\TenantSupportSession`

## Services added (`App\Services\SupportOperations`)

`SupportRedactor`, `SupportException`, `SupportAuditService`,
`SupportTenantHealthService`, `SupportDiagnosticTimelineService`,
`SupportBillingViewerService`, `SupportPaymentViewerService`,
`SupportEntitlementViewerService`, `SupportOnboardingViewerService`,
`SupportAndroidRuntimeViewerService`, `SupportDeviceOperationsService`,
`SupportIncidentService`, `SupportReadOnlyContextService`,
`SupportImpersonationService`, `SupportGovernanceAuditService`,
`SupportGoNoGoService`.

## Controllers / requests / resources added

Controllers: `AdminSupportConsoleController`, `AdminSupportDeviceController`,
`AdminSupportIncidentController`, `AdminSupportSessionController`.
Requests: `StoreSupportIncidentRequest`, `UpdateSupportIncidentRequest`,
`StoreSupportIncidentNoteRequest`, `SupportDeviceRevokeRequest`,
`SupportDeviceReactivateRequest`, `StartSupportReadOnlyContextRequest`,
`StartSupportImpersonationRequest`, `SupportTimelineQueryRequest`.
Resources: `SupportTenantHealthResource`, `SupportTimelineEventResource`,
`SupportBillingSummaryResource`, `SupportPaymentSummaryResource`,
`SupportEntitlementSummaryResource`, `SupportOnboardingSummaryResource`,
`SupportAndroidRuntimeSummaryResource`, `SupportIncidentResource`,
`SupportIncidentNoteResource`, `SupportActionResource`, `SupportSessionResource`.

## Commands added

`support-ops:tenant-health`, `support-ops:timeline`, `support-ops:billing-status`,
`support-ops:payment-status`, `support-ops:entitlement-denials`,
`support-ops:sync-failures`, `support-ops:incident-summary`,
`support-ops:device-action`, `support-ops:governance-audit`,
`support-ops:go-no-go`.

## Key confirmations

- Support console is `platform.admin` only; no tenant/public support route exists.
- Console is read-only by default; every mutation requires an enumerable
  `reason_code` and is audited to `tenant_support_actions` + `admin_audit_logs`.
- Manual suspension always wins (health `critical`); support never marks an invoice
  paid, unlocks entitlement, bypasses settlement, or lifts/reactivates a suspension.
- Device revoke uses the Sprint 34 `DeviceRevocationService`; a revoked device
  stays blocked; reactivation is governed not-supported by default.
- Impersonation is disabled by default; a start attempt is audited DENIED and never
  exposes a raw credential/token.
- Read-only context is time-bound; expiry enforced by service/query check.
- Diagnostic timeline is deterministic; all viewers/notes/timeline output redacted.

## Regression (Sprint 24–34)

All prior go/no-go gates remain registered and green:
`subscription-renewal`, `tenant-lifecycle`, `tenant-plan`,
`report-export-metering`, `usage-ledger`, `export-governance`, `billing`,
`payment-gateway`, `entitlement`, `onboarding`, `android-runtime`.

## Security / PII / secret redaction

`SupportRedactor` redacts all metadata and free text; no command/API/console/smoke
output leaks password/secret/token/api_key/server_key/private_key/sk_live_.

## Rollback

Revert the branch merge — additive tables/services/routes/commands/config removed;
no prior-sprint table or behaviour touched.

## Deferred risks

- Governed impersonation deliberately unimplemented (read-only context covers all
  safe needs).
- Governed device reactivation deferred to the standard Sprint 34 activation flow.
