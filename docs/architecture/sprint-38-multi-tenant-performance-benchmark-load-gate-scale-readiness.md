# Sprint 38 — Multi-Tenant Performance Benchmark, Load Gate & Scale Readiness

Scope: add deterministic, CI-safe performance governance for many tenants, products, POS transactions, Android sync batches/items, import rows, export/report rows, billing/payment webhook events, queue pressure, query/index review, threshold checks, smoke performance, observability integration, and pilot/VPS deployment evidence.

Non-goals: no Android UI rewrite, no external network dependency in CI, no real QRIS/payment gateway credentials, no blind index changes, no Sprint 39 work.

Commercial SaaS chain:
Plan -> Invoice -> Payment Intent -> Gateway Settlement -> Collection -> Entitlement Runtime Access -> Tenant Onboarding -> Android Runtime -> Support Operations -> Observability -> Data Import/Bootstrap -> Performance/Scale Readiness.

Benchmark architecture:
`PerformanceBenchmarkService` orchestrates every profile and records `performance_benchmark_runs` plus `performance_benchmark_steps`. Fixture creation is dry-run by default through `PerformanceFixtureService`. `PerformanceThresholdGateService` fails closed with reason codes. `IndexReviewService` records query-pattern evidence without altering schema by default. `PerformanceObservabilityBridgeService` returns redacted metrics only. `PerformanceDeployGateService` records pilot/VPS gate evidence and never fabricates deployment success.

Profiles:
`ci_smoke` is bounded and required in CI. `local_medium` is developer-controlled. `pilot_vps` is for safe deployed validation. `manual_heavy` exists for explicit manual runs only and is never default.

Fixture lifecycle:
Benchmark fixtures use the `sprint38` benchmark key convention and must be deterministic, tenant-isolated, redacted, and cleanable by benchmark-created scope only. Destructive cleanup must not affect non-benchmark tenant data.

Tenant isolation proof:
The multi-tenant step records isolated counts and uses safe service paths. No output includes tenant PII, raw sync payloads, import rows, payment payloads, secrets, addresses, phone numbers, emails, or raw gateway signatures.

Benchmark areas:
Product benchmark verifies product/category lookup scale. POS transaction benchmark references `SaleService` and duplicate-safe client references. Android sync benchmark references Sprint 34 `AndroidSyncIngestionService`. Import benchmark references Sprint 37 `TenantDataImportService`. Export/report benchmark preserves Sprint 27-29 metering through usage ledger services. Billing/payment benchmark references Sprint 30/31 invoice, payment intent, and webhook paths. Queue pressure is bounded and does not require external queue infrastructure in CI.

Index/query review:
Sprint 38 records query patterns for tenant, product, POS sale, Android sync, import, export/report, billing, payment, entitlement, onboarding, support, observability, and queue areas. It does not add or remove indexes without evidence.

Routes:
All admin routes live under `api/v1/admin/performance/*` behind `platform.admin`: profiles, runs, run detail, steps, threshold-check, query-reviews, deploy-checks, and governance. Mutation routes require `reason_code`.

Commands:
`performance:profile-summary`, `performance:fixture-build`, `performance:run`, `performance:threshold-check`, `performance:query-review`, `performance:queue-pressure`, `performance:smoke`, `performance:deploy-check`, `performance:governance-audit`, and `performance:go-no-go`.

Data model:
`performance_benchmark_runs`, `performance_benchmark_steps`, `performance_query_reviews`, and `performance_deploy_checks`.

Deployment process:
Confirm pilot/VPS credentials/config, create DB/config/runtime backups, deploy the tested merge commit or GO candidate, run migrations/cache/build/service reloads, verify health, run `scripts/sprint38_deploy_smoke.sh`, run `performance:deploy-check`, run `performance:smoke --profile=pilot_vps`, run `observability:health`, and record evidence in `docs/deployment/sprint-38-pilot-vps-deploy-evidence.md`. Missing credentials are DEPLOY BLOCKED, not success.

Rollback:
Restore DB backup, restore config/runtime backup if touched, redeploy previous GO tag, run migrations/status checks, clear/build cache, reload services, run health/smoke, and record the rollback evidence.

Rules:
- `PERF-R001` — Performance benchmarks must use PerformanceBenchmarkService.
- `PERF-R002` — Benchmark fixtures must be deterministic.
- `PERF-R003` — Benchmark data must be tenant-isolated.
- `PERF-R004` — CI profile must be bounded and non-flaky.
- `PERF-R005` — Heavy profile must not run by default in CI.
- `PERF-R006` — Performance commands must require explicit profile.
- `PERF-R007` — Destructive cleanup must be limited to benchmark-created data.
- `PERF-R008` — Benchmark output must not leak PII/secrets/raw payloads.
- `PERF-R009` — Many-tenant benchmark must verify tenant isolation.
- `PERF-R010` — Product benchmark must verify product/category lookup performance.
- `PERF-R011` — POS transaction benchmark must use existing SaleService/domain service.
- `PERF-R012` — POS benchmark must not double-create transactions.
- `PERF-R013` — Android sync benchmark must use Sprint 34 sync service and idempotency.
- `PERF-R014` — Import benchmark must use Sprint 37 DataImport services.
- `PERF-R015` — Export/report benchmark must preserve Sprint 27-29 metering/governance.
- `PERF-R016` — Billing benchmark must use Sprint 30 invoice/collection services.
- `PERF-R017` — Payment webhook benchmark must use Sprint 31 mock/safe gateway events.
- `PERF-R018` — Entitlement benchmark must use Sprint 32 EntitlementAccessService.
- `PERF-R019` — Onboarding benchmark must preserve Sprint 33 provisioning semantics.
- `PERF-R020` — Support diagnostics benchmark must preserve Sprint 35 read-only support semantics.
- `PERF-R021` — Observability benchmark must integrate Sprint 36 metrics/anomaly services.
- `PERF-R022` — Queue pressure benchmark must not require external queue infra in CI.
- `PERF-R023` — Failed job diagnostics must remain redacted under load.
- `PERF-R024` — Index additions must be justified by query pattern evidence.
- `PERF-R025` — Index review must not remove prior indexes without explicit proof.
- `PERF-R026` — Threshold gate must fail closed on regression.
- `PERF-R027` — Threshold gate must produce explainable reason codes.
- `PERF-R028` — Benchmark snapshots must be auditable and redacted.
- `PERF-R029` — Performance smoke must run in CI.
- `PERF-R030` — Deploy performance smoke must run after pilot/VPS deployment.
- `PERF-R031` — No benchmark may mark invoice paid outside trusted billing/payment services.
- `PERF-R032` — No benchmark may unlock entitlement outside trusted collection state.
- `PERF-R033` — No benchmark may bypass manual suspension.
- `PERF-R034` — No benchmark may reactivate tenant/device.
- `PERF-R035` — Prior Sprint 24-37 gates must remain green.
- `PERF-R036` — Go/no-go must verify multi-tenant, product, POS, Android sync, import, export/report, billing/payment, queue, index/query, observability, smoke-performance, and deploy evidence.

Deferred risks:
Pilot/VPS execution requires real deployment credentials and an agreed target. Heavy load is separated from default CI to prevent flakiness.
