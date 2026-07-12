# UIX-4 — Deployment Evidence & GO Decision

Real, observed evidence for the UIX-4 Tenant Owner Web Console pilot deployment.
Values below are captured during the actual deploy over the encrypted operator
channel; no placeholder or assumed evidence is permitted at GO (rule 90).

> Status: pending deployment. This record is completed with observed command
> output at deploy time and, per repository convention, closed via an evidence
> PR after merge.

## Release identity
- Code PR: _to be recorded_
- Merge commit (final release commit): _to be recorded_
- Local / origin / VPS HEAD equality: _to be recorded_

## Pre-deploy
- AISH HEAD before deploy (rollback point): _observed_
- DaengtisiaMS baseline HEAD: `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4`
- DB backup of `aish_pos_pilot`: _observed_

## Deploy
- `git pull --ff-only` to merge commit: _observed_
- `migrate:status` (expect no new UIX-4 migrations): _observed_
- `config:cache` / `route:cache` / `view:cache`: _observed_
- `systemctl reload php8.5-fpm-aish-pos` / `nginx`: _observed_

## Runtime verification (over encrypted channel)
- `GET /owner/login` → _observed http code_
- `GET /health/live` / `GET /health/ready` → _observed_
- `GET /admin/login` (prior surface, no regression) → _observed_
- Authenticated owner: dashboard, outlets, devices, subscription, usage,
  operations → _observed_; logout → _observed_
- Security: guest `/owner` → redirect; admin session → `/owner` denied; owner
  session → `/admin` denied; foreign outlet/device → 404; `Cache-Control:
  no-store` present → _observed_

## DaengtisiaMS non-regression
- DMS HEAD after deploy equals baseline `8b0bb6a…`: _observed_
- DMS `/` and `/login` healthy; `php8.3-fpm` + `daengtisiams-queue-worker`
  untouched: _observed_

## GO decision
- Who / when / what verified: _to be recorded_
- Public plaintext HTTP with real tenant data: NOT used (encrypted channel only).
- GO tag: `uix-4-tenant-owner-web-console-go` (annotated, on the final release
  commit; existing GO tags left immutable).
