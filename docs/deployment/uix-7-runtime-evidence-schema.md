# UIX-7 Runtime Evidence Manifest — Schema

The machine-parseable source of truth for UIX-7 runtime closure is
`docs/deployment/uix-7-runtime-evidence.json`. It is validated by
`scripts/uix7_runtime_closure_gate.sh` (structured, classification-aware,
fail-closed). This schema is normative; the gate rejects any manifest that
violates it.

## Top-level object

| Field | Type | Rule |
| --- | --- | --- |
| `policy_version` | string | Governance policy version (e.g. `1.0.0`). REQUIRED. |
| `go_tag` | string | Exact target GO tag name. REQUIRED. |
| `candidate_commit` | string \| null | The final source candidate commit SHA. May be null in `preflight`; MUST be a real SHA in `closure`. |
| `app_source_unchanged_since` | string \| null | Commit SHA at which the tested APK's app source was last changed. Lets physical evidence captured at an ancestor commit remain valid when later commits touch only docs/rules/gate/tests. |
| `decision` | string | Free text. In `closure` MUST begin with `GO`. |
| `scenarios` | array | One record per runtime scenario. REQUIRED, non-empty. |

## Scenario record

| Field | Type | Rule |
| --- | --- | --- |
| `scenario_id` | string | Stable id (e.g. `R11`). REQUIRED, unique. |
| `scenario_name` | string | Human name. REQUIRED. |
| `classification` | enum | `hardware_independent` \| `hardware_neutral` \| `hardware_dependent` \| `oem_specific`. REQUIRED. |
| `evidence_source` | enum | `physical` \| `emulator` \| `automated_test` \| `database` \| `ci` \| `vps` \| `pending`. REQUIRED. |
| `result` | enum | `PASS` \| `N/A` \| `PENDING` \| `BLOCKED` \| `FAIL`. REQUIRED. |
| `commit_sha` | string | REQUIRED non-empty for `PASS`. |
| `app_version` | string | REQUIRED non-empty for a `PASS` produced by a build (`physical`/`emulator`). |
| `apk_sha256` | string | REQUIRED non-empty for a `PASS` produced by a build. 64 hex chars. |
| `build_variant` | string | e.g. `pilot`. REQUIRED for a build-produced `PASS`. |
| `environment` | string | Device/AVD description. REQUIRED for a `PASS`. |
| `executed_at` | string | Timestamp/date. REQUIRED for a `PASS`. |
| `verification_method` | string | How it was verified. REQUIRED non-empty for a `PASS`. |
| `evidence_reference` | string | Pointer to the artifact/observation. REQUIRED non-empty for `PASS` and for `N/A` (the reason). |

## Validation rules (enforced by the gate)

Always (both modes):

1. Manifest is valid JSON and has all required top-level fields.
2. Every scenario has all required fields present and typed.
3. `classification`, `evidence_source`, `result` are within their enums.
4. `scenario_id` values are unique.
5. A `PASS` row MUST have a non-`pending` `evidence_source`, a non-empty
   `commit_sha`, `verification_method`, and `evidence_reference`.
6. A build-produced `PASS` (`physical`/`emulator`) MUST have non-empty
   `app_version`, `apk_sha256` (64 hex), `build_variant`, `environment`,
   `executed_at`.
7. A `PASS`/`PENDING`/`BLOCKED` row whose `classification` is `hardware_dependent`
   or `oem_specific` MUST NOT use `evidence_source: emulator` (BLOCKING).
8. Emulator evidence is accepted for `hardware_independent`/`hardware_neutral`.
9. An `N/A` row MUST carry a non-empty `evidence_reference` (the domain reason).
10. No secret/credential pattern anywhere in the manifest.
11. `candidate_commit`, when set, MUST NOT be a placeholder
    (`PENDING`, `TBD`, `TODO`, `FILL_ME`, `<...>`).
12. A `PASS` row's `commit_sha` MUST equal `candidate_commit` or
    `app_source_unchanged_since` when `candidate_commit` is set (staleness guard).

Closure mode (`UIX7_CLOSURE_GATE_MODE=closure`) additionally:

13. `candidate_commit` is a real SHA (set, non-placeholder).
14. No `PENDING`, `BLOCKED`, or `FAIL` row remains.
15. `decision` begins with `GO`.
16. The target `go_tag` MUST NOT already exist as a git tag on a different commit.

The gate is fail-closed: any violation exits non-zero and blocks GO.
