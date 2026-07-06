# Project Rules

The canonical source of truth for this project is:

`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`

No implementation may contradict this document unless the foundation document is explicitly updated first.

Mandatory rules:

1. This project is a multi-tenant Android POS SaaS, not a single-store POS.
2. All operational data must be tenant-isolated.
3. QRIS must be dynamic and backend-driven.
4. Payment gateway credentials must never exist in Android code.
5. Cash transaction may work offline.
6. QRIS transaction requires online connectivity.
7. Android must remain lightweight for older devices.
8. Subscription and device limit must be part of the SaaS foundation.
9. Every sprint must reference this foundation document.
10. Docs-only output is not accepted for implementation sprints unless explicitly requested.

## Sprint Execution Rule

Every sprint must:

1. Reference `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`.
2. Produce validation evidence in `docs/sprints/`.
3. Include clear GO / NO-GO criteria.
4. Avoid implementation that contradicts the foundation.
5. Avoid docs-only output for implementation sprints unless explicitly requested.

## Foundation Lock Index

This project is governed by:

1. `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
2. `docs/sprints/sprint-0-project-setup.md`
3. `docs/sprints/sprint-1-saas-tenant-foundation.md`
4. `docs/sprints/sprint-2-product-foundation.md`

No sprint may contradict these documents unless the canonical foundation is explicitly updated first.

## Sprint 0 Runtime Rule

Sprint 0 established the controlled monorepo structure:

- `backend/` for Laravel API
- `android/` for native Android Kotlin
- `docs/` for foundation and sprint evidence
- `scripts/` for validation smoke scripts
- `.github/workflows/` for CI

Future work must preserve this structure unless the foundation is updated.

## Sprint 1 Multi-Tenant Runtime Rule

Starting Sprint 1, backend runtime implementation must enforce tenant isolation.

Mandatory:

1. Tenant-owned data must include `tenant_id`.
2. Store-owned data must include both `tenant_id` and `store_id` where applicable.
3. Tenant context must come from authenticated user/session context, not arbitrary client input.
4. Any client-provided store selector must be validated against the authenticated user tenant.
5. Tests must prove that tenant A cannot access tenant B data.

## Sprint 2 Product Foundation Runtime Rule

Starting Sprint 2, product catalog runtime implementation must enforce tenant isolation and Android sync readiness.

Mandatory:

1. Product categories must be tenant-owned.
2. Products must be tenant-owned.
3. Store-specific product price overrides must be tenant-owned and store-scoped.
4. Product category and product APIs must use authenticated tenant context.
5. Client input may not assign arbitrary `tenant_id`.
6. Any `store_id` input must be validated against the authenticated tenant.
7. Tenant A must never read, create, update, delete, or sync product data from tenant B.
8. Product sync endpoints must support incremental sync using `updated_since`.
9. Product sync output must be lightweight for older Android devices.
10. Product foundation must not implement sales, payment, QRIS, printer, or inventory movement runtime yet.
