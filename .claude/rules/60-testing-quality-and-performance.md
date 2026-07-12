# 60 — Testing, Quality & Performance

Test expectations and quality bar for Aish POS.

## Test commands
- Full suite: `php artisan test` from `backend/` (in-memory sqlite).
- Targeted: `php artisan test --filter <TestName>` while iterating.
- Platform-admin scenarios build the actor with `User::factory()->platformAdmin()`.
- Rule presence check: `scripts/verify_application_foundation_rules.sh`.
- Design gates: `scripts/uix1_design_gate.sh`, `scripts/uix2_design_gate.sh`,
  `scripts/uix3_design_gate.sh`.

## Coverage expectations
- New service logic ships with feature/unit tests. New middleware, admin route, or guard
  path ships with tests covering allow AND deny outcomes.
- Cover the boundaries: tenant isolation, platform-admin gate, lifecycle/entitlement/usage
  decisions, redaction, and audit writes.
- The `/admin/*` console and `/api/v1/admin/*` API are tested against the same service
  outcomes so the two surfaces stay in parity.

## Green means green
- Do not merge with failing or skipped-to-hide tests. A change is complete only when the
  full suite passes and the authoritative CI workflows (rule 70) are green.
- When adding methods to shared API service contracts, update the corresponding test
  fakes/mocks so unrelated suites stay green.

## Performance
- Avoid N+1 queries on list/admin endpoints; eager-load intentionally.
- Metering, ledger scans, and admin aggregations must be bounded; no unbounded full-table
  scans in a request path. Heavy or scheduled work belongs in the queue worker.
- Blade renders server-side without a client build step; keep pages lightweight.
