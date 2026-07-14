# ADR 0001 — Authoritative emulator evidence for hardware-independent Android runtime closure

- Status: **Accepted**
- Date: 2026-07-14
- Deciders: Release Governance (UIX-7)
- Supersedes: prior blanket "physical-device only" GO-evidence stance for UIX-7
- Related: `.claude/rules/55-android-cashier-experience.md` (UIX7-R062,
  UIX7-R071..R080), `docs/governance/android-runtime-evidence-governance.md`,
  rule 90 (release evidence & GO tag).

## Context

UIX-7 (Android Cashier remediation) is implemented, merged, and CI-green. Its
transaction/financial/idempotency core is physical-device verified (device
activation, tenant/outlet/role binding, online transaction, financial parity,
stable `client_reference`, online idempotency, double-submit). The remaining
GO-blocking rows — offline durable save, process-kill restoration, reconnect,
idempotent sync, receipt/history parity, accessibility semantics, and
crash/ANR/log inspection — do not depend on device-specific hardware. Under the
previous rule they still required a physical-device mirror, which stalled GO on
work that an emulator can validate identically.

## Problem

Requiring physical-device evidence for hardware-independent behavior:

- adds no fidelity for persistence/sync/accessibility/log scenarios,
- indefinitely defers GO on otherwise-complete, defect-free work,
- while the genuinely device-specific risks (peripherals, OEM behavior) still
  need a physical device.

## Options

1. **Keep physical-only** — highest ceremony, no added assurance for
   hardware-independent rows, GO stays blocked.
2. **Emulator for everything** — rejected; erases the real physical-only risk
   surface (peripherals, OEM background restrictions, hardware keystore).
3. **Classification-driven admissibility (chosen)** — emulator authoritative for
   hardware-independent rows; physical required for hardware-dependent /
   OEM-specific rows; source always explicit and never relabelled.

## Decision

Adopt option 3. Runtime evidence source is governed by a per-scenario hardware
`classification`. Controlled emulator evidence is authoritative for
`hardware_independent` scenarios; physical-device evidence remains REQUIRED for
`hardware_dependent` and `oem_specific` scenarios. Every row is source-attributed
and commit-bound, and emulator evidence is never labelled or aggregated as
physical.

## Emulator use boundaries

Admissible on emulator: Room/local-DB persistence, cart durability, offline save,
process-kill/recreation/restart, WorkManager scheduling, network loss/reconnect,
API sync, idempotency, receipt/history parity, font scaling, accessibility
semantics, touch targets, crash/ANR/log/cleartext/secret inspection.

## Hardware-required exceptions (physical device only)

Camera/barcode scanner, Bluetooth printer, USB printer, NFC, biometric,
hardware-backed keystore, physical payment peripherals, vendor/OEM background
restrictions and permission behavior, and any OEM-specific defect.

## Consequences

- GO can be reached with mixed evidence once genuine emulator evidence for the
  hardware-independent rows is captured and validated by the closure gate.
- The closure gate becomes structured and classification-aware (UIX7-R078); it
  fails closed and rejects emulator evidence for hardware-required rows.
- Evidence integrity is unchanged: still commit-bound, redacted, auditable, and
  non-retroactive (UIX7-R079). Absent evidence never becomes a PASS.

## Migration plan

Existing physical-device evidence is retained as-is. New structured manifest
(`docs/deployment/uix-7-runtime-evidence.yaml`) records each row's source and
classification. The prior physical-device closure doc remains immutable as a
historical record.

## Rollback

Restore the pre-1.0.0 `UIX7-R062` wording and the pre-refactor gate. The manifest
retains `evidence_source` per row, so no audit trail is lost on rollback.

## Release impact

Does not itself create a GO tag. GO remains gated on genuine captured evidence,
authoritative CI, VPS exact-match, DMS non-regression, and immutable prior tags
(rule 90 / UIX7-R066/R070). Emulator evidence must be genuinely captured — this
ADR authorizes its admissibility, never its fabrication.
