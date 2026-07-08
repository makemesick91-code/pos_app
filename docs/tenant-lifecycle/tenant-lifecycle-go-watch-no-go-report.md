# Tenant Lifecycle GO / WATCH / NO-GO Report (Sprint 25)

Evidence template for the Sprint 25 tenant lifecycle gate. Regenerate the JSON
sections from the commands before creating the GO tag.

## Decision contract

`tenant-lifecycle:go-no-go` aggregates:

- **required_commands** — all cumulative Sprint 13–24 gate commands registered.
- **tenant_lifecycle_commands** — the four Sprint 25 commands registered.
- **tenant_lifecycle_docs** — the six required docs present.
- **android_release_readiness** — `scripts/android_release_readiness.sh` present.
- **tenant_lifecycle_readiness** — the readiness decision, which itself embeds:
  - `automation_guardrails` — all nine flags disabled.
  - `lifecycle_status_source` — seven statuses defined, `suspended` blocked.
  - `manual_suspension_store` — both tables present.
  - `tenant_lifecycle_docs` / `tenant_lifecycle_rules` — TLS-R001..R010 locked.
  - `runtime_enforcement` — the enforcement audit decision:
    - `lifecycle_guard_alias` — `tenant.lifecycle` registered.
    - `lifecycle_guard_coverage` — every operational route guarded.
    - `lifecycle_config_contract` — statuses/blocked/rules complete.
    - `lifecycle_guardrails` — automation guardrails disabled.

`FAIL` → NO_GO, `WARN` → WATCH, otherwise GO.

## Commands to run

```bash
php artisan tenant-lifecycle:readiness --json
php artisan tenant-lifecycle:suspension-summary --json
php artisan tenant-lifecycle:enforcement-audit --json
php artisan tenant-lifecycle:go-no-go --json
```

## Latest evidence

- Decision: **GO** (fill from CI / local run).
- Enforcement audit: all operational tenant routes carry `tenant.lifecycle`; no
  unguarded operational routes.
- Guardrails: 9/9 automation flags disabled.
- Rules: TLS-R001..R010 locked in `config/tenant_lifecycle.php` and
  `docs/PROJECT_RULES.md`.

> Paste the `--json` outputs here as part of the GO evidence bundle.
