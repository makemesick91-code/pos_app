# UIX-5 Deployment Evidence — Subscription, Billing & Invoice Console

> STATUS: PENDING DEPLOY. This record is intentionally empty of results until the
> gated deploy runs over an approved encrypted operator channel. No GO tag may be
> pushed until every section below is filled with real, observed output (rule 90).
> Do not fabricate results (UIX5-R028).

## 1. Release identity
- Release commit (local): _pending_
- `origin/main`: _pending_
- VPS `/var/www/aish-pos` HEAD after checkout: _pending_
- Exact-match confirmed (local == origin == VPS): _pending_

## 2. Authoritative CI
- PR workflows green (run URLs + conclusions): _pending_

## 3. Backup (rule 80)
- `aish_pos_pilot` backup artifact + timestamp: _pending_
- Previous release commit recorded: _pending_

## 4. Deploy actions
- composer install (no-dev) output tail: _pending_
- cache rebuild + `chown www-data:www-data storage/framework bootstrap/cache` (UIX5-R026): _pending_
- php8.5-fpm (pool aish-pos) + nginx reload: _pending_

## 5. Runtime verification (encrypted channel, port 8080 IP-restricted)
- `/health/live`, `/health/ready`: _pending_
- Owner: `/owner/billing`, `/owner/billing/invoices`, invoice detail, invoice `/download` (headers): _pending_
- Admin: `/admin/billing`, `/admin/billing/invoices`, invoice detail, `/admin/tenants/{id}/billing`: _pending_
- Cross-tenant 404 + cross-surface denial observed: _pending_
- No public plaintext-HTTP billing exposure (UIX5-R025): _pending_

## 6. DaengtisiaMS non-regression (rule 80)
- DMS HEAD/tag before & after (unchanged): _pending_
- DMS `/`, `/login`, `SELECT 1`, php8.3-fpm, nginx, PostgreSQL healthy: _pending_

## 7. GO decision
- Who / when / what verified: _pending_
- Annotated GO tag `uix-5-subscription-billing-invoice-console-go` on release commit: _pending_
