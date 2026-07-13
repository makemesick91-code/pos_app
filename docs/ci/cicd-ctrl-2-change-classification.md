# CICD-CTRL-2 — Change Classification

`scripts/ci/classify_changes.sh` is the repository-owned, **fail-closed**
authority on whether a change may take the lightweight docs/evidence lane or must
run full authoritative CI. GitHub `paths:`/`paths-ignore:` filters are an
optimization only and never the security boundary (CICD2-R004).

## Interface

```
classify_changes.sh [--strict] <BASE_REF> <HEAD_REF>
# or env: BASE_SHA / HEAD_SHA
# or test override: CLASSIFY_FILES="<newline-separated paths>"
```

Emits `key=value` lines to stdout and (when set) `$GITHUB_OUTPUT`:

| key | meaning |
|---|---|
| `full_ci_required` | authoritative full CI required |
| `docs_only` / `evidence_only` | lightweight lane eligible |
| `android_changed` / `backend_changed` | targeted-lane flags |
| `database_changed` / `dependencies_changed` / `api_contract_changed` | full-CI subclasses |
| `security_sensitive_changed` / `rules_changed` / `workflow_changed` / `deployment_changed` | full-CI subclasses |
| `classification` | `full_ci` \| `docs_only` \| `evidence_only` |
| `changed_files_count` / `reason` | diagnostics |

## Fail-closed guarantees

- Unresolved diff (missing/shallow base or head), empty change set, or git failure
  → `full_ci_required=true`.
- Any path not inside the strict lightweight allowlist → `full_ci_required=true`.
- Renames contribute **both** old and new paths (a source→docs rename escalates).
- Deletes are classified by their path (a deleted security test → full).
- Non-lightweight file extensions (anything outside
  `md,txt,json,csv,png,jpg,jpeg,gif,svg,webp`) → full.

## Lightweight allowlist (the ONLY way to avoid full CI)

- **evidence**: `docs/deployment/**`, `docs/evidence/**` (non-executable extensions)
- **docs**: `docs/**` and root-level `*.md`, EXCLUDING governing content

Governing content is never lightweight: `.claude/**`, `CLAUDE.md`,
`CLAUDE.local.md`, `AGENTS.md`, `docs/PROJECT_RULES.md`, `docs/governance/**`,
`docs/foundation/**` (CICD2-R005). Executable/plumbing paths are never lightweight:
`.github/**`, `scripts/**`, `backend/**`, `android/**`, lockfiles, gradle/composer
build files, Dockerfiles, nginx/conf.

## Verified scenarios (`tests/ci/classify_changes_test.sh`, 26/26)

Android-only, backend-only, docs-only, evidence-only, `.claude` rules, workflow,
script, dependency lock, migration, mixed docs+source, rename source→docs, unknown
top-level path, deleted security test, evidence+executable script, `AGENTS.md`,
`CLAUDE.md`, `PROJECT_RULES.md`, governance/foundation docs, root `README.md`,
evidence screenshot `.png`, `docs/deployment/*.sh` (NOT light), security middleware,
routes, gradle build config, empty change set. All source/mixed/unknown/governing
cases require full CI; only strict docs/evidence take the lightweight lane.
