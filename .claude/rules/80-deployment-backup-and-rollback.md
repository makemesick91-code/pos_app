# 80 — Deployment, Backup & Rollback

Shared-VPS deployment rules and the DaengtisiaMS non-regression mandate.

## Isolation on the shared VPS
- Aish POS co-tenants a VPS with DaengtisiaMS (asia-dental-lab-v2). Aish is fully isolated:
  - php8.5-fpm pool `aish-pos`
  - nginx site `aish-pos` on port 8080
  - systemd unit `aish-pos-queue-worker`
  - database `aish_pos_pilot`
- These names are fixed. Deployments touch only Aish resources.

## DaengtisiaMS is off-limits (non-regression)
- DaengtisiaMS runs on php8.3-fpm and MUST NEVER be modified. Aish work must not touch
  php8.3, the DMS user, or DMS nginx/systemd/database.
- Every deploy is bracketed by a DMS non-regression check (before and after). If DMS is
  affected in any way, the change is NO-GO and must be rolled back immediately.

## Transport / exposure
- No HTTPS/domain yet. Public plaintext admin exposure is NO-GO. Port 8080 stays
  IP-restricted; the admin console is reached only via an encrypted operator channel
  (SSH tunnel/VPN). Do not open it to the public internet.

## Backup before change
- Take a database backup (of `aish_pos_pilot`) and note the current release commit before
  applying migrations or deploying. Migrations must be reversible or paired with a tested
  recovery step.

## Rollback readiness
- A deploy is only allowed if rollback is possible: previous release commit recorded,
  backup captured, and a known-good state to return to.
- After deploy, run runtime verification (health/live, health/ready, smoke) plus the DMS
  non-regression check before declaring success.
