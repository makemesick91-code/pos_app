# Android Runtime Evidence Governance

Policy version: **1.0.0** (introduced by UIX-7 emulator-evidence unblock)
Status: **Active**
Owning surface: Android Cashier (UIX-7) and every subsequent Android sprint (UIX-8+).
Enforced by: `scripts/uix7_runtime_closure_gate.sh` + `tests/ci/uix7_runtime_closure_gate_test.sh`.
Related rules: `.claude/rules/55-android-cashier-experience.md` (UIX7-R062 revised,
UIX7-R071..UIX7-R080), rule 90 (release evidence), ADR
`docs/adr/0001-authoritative-emulator-evidence-for-hardware-independent-runtime.md`.

## 1. Purpose

This policy defines **which runtime evidence sources are authoritative for a GO
decision, and for which scenarios.** It supersedes the previous blanket
requirement that *all* Android runtime GO evidence be captured on a physical
device. It does **not** weaken evidence integrity: every claim must still be
real, source-attributed, commit-bound, and auditable.

## 2. Core principle

> Controlled Android emulator evidence MAY be used as authoritative GO evidence
> for runtime scenarios that are **hardware-independent**, provided the artifact,
> commit, environment, scenario, result, and verification method are documented
> and auditable. Physical-device evidence REMAINS REQUIRED for
> hardware-dependent and OEM-specific scenarios.

Two invariants sit above everything else:

- **Emulator evidence MUST NEVER be represented or labelled as physical-device
  evidence.**
- **Evidence source MUST remain explicit, immutable, and auditable.**

## 3. Scenario classification

Every runtime scenario carries exactly one `classification`:

| Classification | Meaning | Authoritative source |
| --- | --- | --- |
| `hardware_independent` | Outcome does not depend on device-specific hardware/OEM behavior (persistence, sync, idempotency, receipt/history parity, accessibility semantics, logs). | Emulator **or** physical (either is authoritative) |
| `hardware_neutral` | Behaves identically on emulator or device but has existing physical evidence that is retained. | Physical or emulator |
| `hardware_dependent` | Depends on physical hardware (camera, scanner, Bluetooth/USB printer, NFC, biometric, hardware keystore, physical payment peripheral). | **Physical device REQUIRED** |
| `oem_specific` | Depends on vendor/OEM behavior (background restrictions, OEM permission prompts, vendor power management). | **Physical device REQUIRED** |

### 3.1 Emulator-admissible (hardware-independent) scenarios

Room/local-DB persistence; cart durability; offline transaction save;
process-kill; process recreation; application restart; WorkManager scheduling;
network loss; reconnect; API sync; idempotency; receipt parity; history parity;
font scaling; accessibility semantics; touch-target inspection; crash
inspection; ANR inspection; log inspection; cleartext inspection;
secret-in-log inspection.

### 3.2 Physical-device-required scenarios

Camera / barcode scan; Bluetooth printer; USB printer; NFC; biometric;
vendor-specific background restrictions; OEM permission behavior;
hardware-backed keystore behavior; physical payment peripherals; any
defect reproducible only on specific hardware.

## 4. Foundation (permanent baseline for UIX-7 and all future Android sprints)

### 4.1 Runtime evidence foundation

1. All runtime evidence MUST name its source (physical device / emulator /
   automated test / VPS / database / CI).
2. Emulator MAY be authoritative **only** for hardware-independent scenarios.
3. Physical device REMAINS mandatory for hardware-dependent and OEM-specific
   scenarios.
4. Mixed evidence (physical for some rows, emulator for others) is permitted.
5. Emulator evidence MUST NOT be labelled physical-device evidence.
6. Evidence MUST be bound to: exact commit SHA, app version, APK SHA-256,
   build variant, test timestamp, runtime environment.
7. Evidence from different commits MUST NOT be combined as final closure without
   reconfirmation.
8. A GO tag MUST NOT be created on stale evidence.
9. Emulator MUST use an app-supported API level.
10. A release-equivalent APK MUST be used for authoritative runtime evidence.
11. Debug-only behavior MUST NOT be the sole GO proof.
12. AVD configuration MUST be documented.
13. Network state MUST be documented.
14. Process-kill method MUST be documented.
15. Database verification MUST use scoped queries.
16. Idempotency evidence MUST prove the final sale/payment count.
17. Receipt/history MUST be compared to the persisted transaction.
18. Accessibility evidence MUST state the inspection method.
19. Crash/ANR/log evidence MUST come from a clearly-bounded test window.
20. A failed scenario STAYS `FAIL`/`BLOCKED`; it is never converted to `N/A`
    without a legitimate domain reason.

### 4.2 Closure gate foundation

21. The closure gate MUST NOT depend only on searching for the string `PASS`.
22. The gate MUST validate structured, auditable evidence.
23. The gate MUST check source and hardware classification.
24. The gate MUST reject emulator evidence for a hardware-required scenario.
25. The gate MUST accept emulator evidence for an eligible scenario.
26. The gate MUST reject evidence with no commit SHA.
27. The gate MUST reject an empty APK checksum.
28. The gate MUST reject stale evidence (commit mismatch with the candidate).
29. The gate MUST check authoritative CI result (release/closure context).
30. The gate MUST check PR merged state (release/closure context).
31. The gate MUST check local/origin/VPS exact-match (closure context).
32. The gate MUST check the target tag does not already exist before creation.
33. The gate MUST have regression tests.
34. The gate MUST fail closed on incomplete evidence.

### 4.3 Android transaction foundation

35. A stable `client_reference` MUST be used.
36. A retry for the same cart MUST reuse the same key.
37. An unknown network result MUST be reconciled before creating a new
    transaction.
38. Double-submit protection MUST be preserved.
39. An offline transaction MUST be durable across process kill.
40. Reconnect sync MUST be idempotent.
41. Receipt and history MUST come from the authoritative persisted transaction.
42. The UI MUST NOT be the financial source of truth.
43. Tenant/outlet/device/cashier isolation MUST be preserved.
44. Emulator governance MUST NOT weaken transaction safety.

### 4.4 Security & evidence foundation

45. Sensitive screenshots MUST NOT be auto-committed.
46. Secrets, tokens, Authorization headers, and credentials MUST NOT appear in
    evidence.
47. Test cleanup MUST be tenant-scoped and transactional.
48. A backup MUST be taken before destructive cleanup.
49. Prior GO tags are immutable.
50. This foundation is the mandatory baseline for the next Android sprint,
    including UIX-8.

## 5. What this policy does NOT change

- It is not retroactive: it never turns previously-absent evidence into a PASS.
- It does not make debug-only builds sufficient for GO.
- It does not relax financial, idempotency, tenant-isolation, or leakage gates.
- It does not permit relabelling emulator evidence as physical.
- Existing physical-device evidence remains valid and retained.

## 6. Rollback

If this policy is found to admit unsafe evidence, revert to physical-only by
restoring the pre-1.0.0 `UIX7-R062` wording and the pre-refactor gate. Because
the gate is source-controlled and the manifest carries `evidence_source` per
row, a rollback loses no audit trail.
