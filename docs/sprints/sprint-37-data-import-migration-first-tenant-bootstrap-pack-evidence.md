# Sprint 37 Evidence

Implemented:
- CSV templates and parsing for category, product, supplier, customer, initial stock, price, payment method, default settings, and bootstrap-pack sections.
- Governed XLSX deferral with no formula/macro evaluation.
- Dry-run default and explicit execute requirement.
- Import run and row idempotency through hashed idempotency keys and row fingerprints.
- Tenant-isolated domain imports and import tracking tables.
- Initial stock through the existing inventory movement service.
- Rollback limited to import-created rows, with stock reversal movement.
- Redacted run/row resources, audit metadata, and command output.
- Platform-admin API under `api/v1/admin/imports/*`.
- Commands and gates: `import:governance-audit`, `import:go-no-go`, `scripts/sprint37_smoke.sh`.

Validation checklist:
- `IMP-R001`..`IMP-R034` exist in config, `PROJECT_RULES.md`, `pos_foundation.php`, and docs.
- No tenant/public import mutation route exists.
- No invoice paid, entitlement unlock, manual-suspension bypass, device/tenant reactivation, or gateway settlement mutation is performed by import.
- Support bridge reads import summaries safely.
- Observability bridge detects failed/stuck imports as safe signals.

Deferred:
- XLSX support remains deferred until a safe lightweight parser is introduced and formula/macro behavior is locked down by tests.
