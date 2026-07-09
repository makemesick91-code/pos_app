# Sprint 38 Evidence

Status: implementation evidence prepared; pilot/VPS deployment remains required before GO tag.

Runtime shipped:
`config/performance_governance.php`, performance benchmark tracking tables, `App\Services\Performance\*`, performance commands, and `api/v1/admin/performance/*`.

Smoke/performance evidence to record:
- many tenants: `performance:run --profile=ci_smoke`
- many products: `product_catalog` step
- POS transactions: `pos_transaction` step, service path `SaleService`
- Android sync: `android_sync` step, service path Sprint 34 ingestion
- import: `data_import` step, service path Sprint 37 import
- export/report: `export_report` step, usage/metering path
- billing/payment webhook: `billing_payment` step, mock/safe gateway path
- queue pressure: `performance:queue-pressure --profile=ci_smoke`
- index/query review: `performance:query-review --execute`
- threshold gate: `performance:threshold-check`
- observability: redacted metrics, no raw payloads
- tenant isolation: every step records `tenant_isolated=true`

Rules present:
PERF-R001 PERF-R002 PERF-R003 PERF-R004 PERF-R005 PERF-R006 PERF-R007 PERF-R008 PERF-R009 PERF-R010 PERF-R011 PERF-R012 PERF-R013 PERF-R014 PERF-R015 PERF-R016 PERF-R017 PERF-R018 PERF-R019 PERF-R020 PERF-R021 PERF-R022 PERF-R023 PERF-R024 PERF-R025 PERF-R026 PERF-R027 PERF-R028 PERF-R029 PERF-R030 PERF-R031 PERF-R032 PERF-R033 PERF-R034 PERF-R035 PERF-R036

GO/NO-GO:
Run `scripts/sprint38_smoke.sh`, `php artisan performance:governance-audit`, and `php artisan performance:go-no-go`. Use `--require-deploy` only after pilot/VPS evidence exists.
