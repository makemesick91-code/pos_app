# Manual Suspension Precedence over Renewal & Dunning (Sprint 25)

Manual suspension has precedence over Sprint 24 subscription renewal and dunning
automation (TLS-R004). This document records the boundary and the regression
guarantees.

## The boundary

- `tenant_manual_suspensions` is written **only** by
  `TenantSuspensionService` (platform-admin suspend/lift).
- Sprint 24 renewal/dunning services (`SubscriptionRenewalCandidateService`,
  `SubscriptionDunningNoticeService`, `SubscriptionRenewalRunService`,
  `SubscriptionRenewalDecisionService`, …) operate over `TenantSubscription`
  records. They never read/write `tenant_manual_suspensions` and never call the
  suspension service.
- `TenantLifecycleService` checks the manual suspension table **first**. So even
  if the subscription is renewed, in grace, or past due, an active manual
  suspension keeps the tenant `suspended` / blocked.

## Guarantees (enforced by tests)

1. **Renewal/dunning cannot override a manual suspension.** A manually suspended
   tenant that is evaluated by renewal candidate/dunning services stays
   `suspended`; its `tenant_manual_suspensions` ACTIVE row and lifecycle decision
   are unchanged. (`TenantLifecyclePrecedenceTest`.)
2. **No auto-reactivation.** Automation flags
   `dunning_can_override_manual_suspension_allowed` and
   `renewal_can_override_manual_suspension_allowed` are hard-coded `false`;
   enabling either forces `tenant-lifecycle:readiness` / `go-no-go` to NO_GO.
3. **Only an explicit platform-admin lift** clears a manual suspension.
4. **Sprint 24 gates stay green.** The subscription renewal gate commands remain
   registered and are part of the Sprint 25 cumulative gate contract; Sprint 24
   tests continue to pass unchanged.

## Interaction summary

Dunning may still *signal* status through renewal candidates (awareness), but it
is a manual queue only and can never mutate the lifecycle enforcement state. The
authoritative operational-access answer always comes from
`TenantLifecycleAccessGuard`.
